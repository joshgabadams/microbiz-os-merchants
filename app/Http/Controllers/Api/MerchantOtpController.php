<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\SendMerchantOtpRequest;
use App\Http\Requests\Merchant\VerifyMerchantOtpRequest;
use App\Models\MerchantIdentity;
use App\Models\MerchantOtp;
use App\Services\Fineract\FineractClientLookupService;
use App\Services\Notification\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * OTP send/verify for the merchant self-service login/registration flow.
 * Shape matches MERCHANT_API_CONTRACT.md exactly: send() returns a
 * challenge_id (not tied to "whatever the latest OTP for this client is"),
 * verify() operates on that specific challenge_id.
 *
 * Always operates on a fincore_client_id that already has a
 * MerchantIdentity row -- created today via
 * MerchantIdentityController::register() for new applicants. An
 * existing-customer self-service lookup equivalent (account number ->
 * MerchantIdentity) isn't built yet; that's a separate, explicitly
 * flagged gap, not something this controller assumes away.
 */
class MerchantOtpController extends Controller
{
    use ApiResponse;

    protected const OTP_TTL_MINUTES = 10;
    protected const MAX_ATTEMPTS = 5;
    protected const VERIFICATION_TOKEN_TTL_MINUTES = 10;

    public function __construct(
        protected FineractClientLookupService $fineract,
        protected NotificationService $notifications,
    ) {
    }

    public function send(SendMerchantOtpRequest $request)
    {
        $fincoreClientId = $request->validated('fincore_client_id');
        $identity = MerchantIdentity::where('fincore_client_id', $fincoreClientId)->firstOrFail();

        // Re-fetch the phone from Fineract itself -- never trust the
        // locally cached copy for something as sensitive as where an OTP
        // gets sent.
        try {
            $client = $this->fineract->getClient((int) $fincoreClientId);
        } catch (RuntimeException $e) {
            return $this->error('Could not verify the Fincore360 client: ' . $e->getMessage());
        }

        $phone = $client['mobileNo'] ?? null;

        if (! $phone) {
            return $this->error('This Fincore360 client has no phone number on file -- cannot send an OTP.', 422);
        }

        $accountNumber = $request->validated('account_number');

        if ($accountNumber && ($client['accountNo'] ?? null) !== $accountNumber) {
            return $this->error('The account number does not match this Fincore360 client.', 422);
        }

        // Only one active challenge at a time -- invalidate anything
        // unconsumed for this client.
        MerchantOtp::where('fincore_client_id', $fincoreClientId)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(self::OTP_TTL_MINUTES);

        $otp = MerchantOtp::create([
            'challenge_id' => 'otp_' . Str::lower(Str::random(26)),
            'fincore_client_id' => $fincoreClientId,
            'code_hash' => Hash::make($code),
            'expires_at' => $expiresAt,
        ]);

        // Keep the identity's own phone in sync with Fineract's copy while
        // we're here.
        $identity->update(['phone' => $phone]);

        $this->notifications->send($identity, 'sms', 'merchant_otp', ['code' => $code]);

        $responseData = [
            'challenge_id' => $otp->challenge_id,
            'masked_phone' => $this->maskPhone($phone),
            'expires_at' => $expiresAt,
        ];

        // Dev-only convenience so Josh (or anyone without SMS access) can
        // test the flow without a real SMS provider wired up yet.
        // Allowlist, not denylist: only ever appears when APP_ENV is
        // exactly 'local', so an unrecognized/misconfigured environment
        // name defaults to hidden, not leaked. Never remove this check
        // without a real SMS provider replacing it first.
        if (app()->environment('local')) {
            $responseData['dev_otp_code'] = $code;
        }

        return $this->success($responseData, 'OTP sent.');
    }

    public function verify(VerifyMerchantOtpRequest $request)
    {
        $challengeId = $request->validated('challenge_id');
        $code = $request->validated('otp');

        $otp = MerchantOtp::where('challenge_id', $challengeId)->first();

        if (! $otp || $otp->consumed_at) {
            return $this->error('This OTP challenge is no longer valid -- request a new one.', 410);
        }

        if ($otp->expires_at->isPast()) {
            return $this->error('This OTP challenge has expired -- request a new one.', 410);
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            return $this->error('Too many incorrect attempts -- request a new OTP.', 429);
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            return $this->error('Incorrect code.', 422);
        }

        $token = Str::random(64);

        $otp->update([
            'consumed_at' => now(),
            'verification_token_hash' => hash('sha256', $token),
            'verification_token_expires_at' => now()->addMinutes(self::VERIFICATION_TOKEN_TTL_MINUTES),
        ]);

        return $this->success([
            'verified' => true,
            'verification_token' => $token,
        ], 'OTP verified.');
    }

    protected function maskPhone(string $phone): string
    {
        $visible = 4;

        if (strlen($phone) <= $visible) {
            return $phone;
        }

        $hidden = str_repeat('*', strlen($phone) - $visible);

        return $hidden . substr($phone, -$visible);
    }
}
