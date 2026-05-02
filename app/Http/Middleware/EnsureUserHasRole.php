<?php

namespace App\Http\Middleware;

use App\Enums\RoleName;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Authorize the request when the user holds at least one of the named roles.
     *
     * Roles are passed as enum string values (e.g. `role:admin`, `role:sao,admin`).
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user !== null, 401);

        $required = array_map(static fn (string $value): RoleName => RoleName::from($value), $roles);

        abort_unless($user->hasAnyRole($required), 403);

        return $next($request);
    }
}
