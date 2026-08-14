<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Stateless HTTP Basic Auth for the API -- validates email/password on
 * every request against the users table, no session/cookie/token
 * involved. This is the standard most legacy third-party services this
 * app integrates with expect, and matches how FineractClient already
 * authenticates outbound to FinCore.
 *
 * Auth::onceBasic() (not Auth::basic()) is deliberate: the plain
 * ->basic() variant persists a session, which a stateless API has no
 * business creating.
 */
class AuthenticateBasicOnce
{
    public function handle(Request $request, Closure $next)
    {
        if ($response = Auth::onceBasic()) {
            return $response;
        }

        return $next($request);
    }
}
