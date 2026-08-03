<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Identity\MfaService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(protected MfaService $mfaService)
    {
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'mfa_code' => ['nullable', 'string'],
        ]);

        if (! Auth::attempt(['email' => $validated['email'], 'password' => $validated['password']])) {
            return $this->error('Invalid credentials.', 401);
        }

        $user = $request->user();

        // Backward compatible: mfa_enabled defaults to false for every
        // existing user, so this only changes behavior for accounts that
        // have explicitly gone through MfaController::enable().
        if ($user->mfa_enabled) {
            if (! ($validated['mfa_code'] ?? null)) {
                return $this->error('MFA code required.', 401);
            }

            if (! $this->mfaService->verifyForUser($user, $validated['mfa_code'])) {
                return $this->error('Invalid MFA code.', 401);
            }
        }

        $token = $user->createToken('microbiz-api')->plainTextToken;

        return $this->success([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Login successful.');
    }

    public function me(Request $request)
    {
        return $this->success(
            $request->user(),
            'Authenticated user retrieved successfully.'
        );
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully.');
    }
}
