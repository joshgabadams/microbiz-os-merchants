<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level RBAC guard, e.g.:
 *   Route::post('/approvals/{id}/approve', ...)->middleware('permission:approvals.approve');
 *
 * Register the alias in bootstrap/app.php:
 *   $middleware->alias(['permission' => \App\Http\Middleware\EnsurePermission::class]);
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->hasPermission($permission)) {
            return response()->json(['message' => "Missing required permission: {$permission}."], 403);
        }

        return $next($request);
    }
}
