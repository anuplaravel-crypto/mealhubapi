<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Restrict a route to one or more of the four user roles.
 *
 * `auth:sanctum` proves only that a token is valid — not whose role it
 * carries. Without this gate a customer's token can call an admin endpoint,
 * because all four roles share the `users` table and one token type.
 *
 * Usage — always paired with `auth:sanctum`, which must run first:
 *
 *     ->middleware(['auth:sanctum', 'role:admin'])
 *     ->middleware(['auth:sanctum', 'role:admin,restaurant'])
 *
 * The alternative, Sanctum token abilities issued at login, is deliberately
 * not used: a token minted before a role change would keep the old ability.
 * The role is read from the user record on every request instead.
 */
class EnsureUserHasRole
{
    /**
     * @param  string  ...$roles  any one of which grants access
     *
     * @throws AuthenticationException when the request is not authenticated
     * @throws AccessDeniedHttpException when the role does not match
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Only reachable if this middleware is used without auth:sanctum in
        // front of it — report it as unauthenticated (401) rather than
        // forbidden (403), which would imply a valid identity.
        if ($user === null) {
            throw new AuthenticationException;
        }

        if (! in_array($user->role, $roles, true)) {
            throw new AccessDeniedHttpException('This action is not available for your account type.');
        }

        return $next($request);
    }
}
