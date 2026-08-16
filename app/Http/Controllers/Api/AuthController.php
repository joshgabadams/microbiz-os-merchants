<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/**
 * Auth is now HTTP Basic (see AuthenticateBasicOnce) -- there is no
 * separate login/token step and nothing server-side to invalidate on
 * logout, so those endpoints are gone. The frontend "logs in" by
 * attaching a Basic Auth header to this endpoint and checking for a
 * 200; it "logs out" by discarding the credential locally.
 *
 * MFA enforcement (previously checked here on login) has no home under
 * per-request Basic Auth -- there's no field to carry a one-time code.
 * Left MfaController/MfaService in place (0 users currently have
 * mfa_enabled), but nothing calls verifyForUser() anymore.
 */
class AuthController extends Controller
{
    use ApiResponse;

    public function me(Request $request)
    {
        return $this->success(
            $request->user(),
            'Authenticated user retrieved successfully.'
        );
    }
}
