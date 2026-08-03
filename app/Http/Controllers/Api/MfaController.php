<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Identity\MfaService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class MfaController extends Controller
{
    use ApiResponse;

    public function __construct(protected MfaService $mfaService)
    {
    }

    public function setup(Request $request)
    {
        $secret = $this->mfaService->generateSecret();
        $qrCodeUrl = $this->mfaService->getQrCodeUrl($request->user(), $secret);

        return $this->success([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ], 'Scan the QR code with your authenticator app, then confirm with a code via /mfa/enable.');
    }

    public function enable(Request $request)
    {
        $validated = $request->validate([
            'secret' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $enabled = $this->mfaService->enable(
            $request->user(),
            $validated['secret'],
            $validated['code']
        );

        if (! $enabled) {
            return $this->error('Invalid verification code. MFA was not enabled.', 422);
        }

        return $this->success([
            'recovery_codes' => $request->user()->fresh()->mfa_recovery_codes,
        ], 'MFA enabled successfully. Store these recovery codes somewhere safe -- they will not be shown again.');
    }

    public function disable(Request $request)
    {
        $this->mfaService->disable($request->user());

        return $this->success(null, 'MFA disabled successfully.');
    }
}
