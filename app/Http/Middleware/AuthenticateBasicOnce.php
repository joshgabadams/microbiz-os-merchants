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
 *
 * Always resolved against the explicit 'web' guard, never the bare
 * Auth facade default. The bare form resolves against whatever guard
 * Auth::shouldUse() last pointed at -- something as unrelated as a test
 * calling ->actingAs($user, 'sanctum') earlier in the same request
 * lifecycle silently redirects it to a guard (Sanctum's RequestGuard)
 * that has no onceBasic() method at all, a fatal error. Pinning the
 * guard makes this middleware's behavior independent of that global,
 * mutable state.
 */
class AuthenticateBasicOnce
{
    public function handle(Request $request, Closure $next)
    {
        if ($response = Auth::guard('web')->onceBasic()) {
            return $response;
        }

        return $next($request);
    }
}
