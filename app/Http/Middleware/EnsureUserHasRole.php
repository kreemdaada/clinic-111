<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware that restricts access to users with allowed roles.
 *
 * Usage: `->middleware('role:admin,accountant')` on web and API routes.
 */
class EnsureUserHasRole
{
    /**
     * Verify the authenticated user has one of the required roles.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Closure  $next  Next middleware / controller.
     * @param  string  ...$roles  Allowed role slugs (`admin`, `accountant`, `viewer`).
     * @return Response Continues pipeline or aborts 401/403.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        }

        $allowedRoles = collect($roles)
            ->map(fn (string $role) => UserRole::tryFrom($role))
            ->filter()
            ->all();

        if (! in_array($user->role, $allowedRoles, true)) {
            abort(Response::HTTP_FORBIDDEN, 'Insufficient permissions.');
        }

        if (! $user->is_active) {
            abort(Response::HTTP_FORBIDDEN, 'Account deactivated.');
        }

        return $next($request);
    }
}
