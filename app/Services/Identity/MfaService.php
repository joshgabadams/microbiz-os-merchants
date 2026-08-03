<?php

namespace App\Services\Identity;

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

/**
 * Time-based one-time password (TOTP) multi-factor authentication.
 *
 * Requires pragmarx/google2fa: composer require pragmarx/google2fa
 * This was not previously a dependency of this project -- confirm it's
 * installed before deploying this code.
 */
class MfaService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function getQrCodeUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('app.name', 'MicroBiz OS'),
            $user->email,
            $secret,
        );
    }

    /**
     * Confirms the user actually has their authenticator app configured
     * correctly (by requiring a valid code) before turning MFA on --
     * enabling MFA with an unconfirmed secret would risk locking the
     * user out immediately.
     */
    public function enable(User $user, string $secret, string $verificationCode): bool
    {
        if (! $this->verifyCode($secret, $verificationCode)) {
            return false;
        }

        $user->update([
            'mfa_enabled' => true,
            'mfa_secret' => encrypt($secret),
            'mfa_recovery_codes' => $this->generateRecoveryCodes(),
        ]);

        return true;
    }

    public function disable(User $user): void
    {
        $user->update([
            'mfa_enabled' => false,
            'mfa_secret' => null,
            'mfa_recovery_codes' => null,
        ]);
    }

    public function verifyCode(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code);
    }

    /**
     * Verifies a code against a user's already-enabled MFA, accepting
     * either a real-time TOTP code or a one-time recovery code.
     */
    public function verifyForUser(User $user, string $code): bool
    {
        if (! $user->mfa_enabled || ! $user->mfa_secret) {
            return false;
        }

        if (in_array($code, $user->mfa_recovery_codes ?? [], true)) {
            $this->consumeRecoveryCode($user, $code);

            return true;
        }

        return $this->verifyCode(decrypt($user->mfa_secret), $code);
    }

    protected function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn () => strtoupper(bin2hex(random_bytes(5))))
            ->toArray();
    }

    protected function consumeRecoveryCode(User $user, string $code): void
    {
        $remaining = array_values(array_diff($user->mfa_recovery_codes ?? [], [$code]));
        $user->update(['mfa_recovery_codes' => $remaining]);
    }
}
