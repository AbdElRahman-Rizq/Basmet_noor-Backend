<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects authenticated requests made by an inactive or soft-deleted account.
 *
 * Sanctum keeps resolving an existing personal access token even after the
 * account is deactivated, so this check runs on every `auth:api` route
 * (D-055). Raising AuthenticationException renders the standard
 * 401 UNAUTHENTICATED envelope.
 */
final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ($user->trashed() || ! $user->is_active)) {
            throw new AuthenticationException;
        }

        return $next($request);
    }
}
