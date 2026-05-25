<?php

namespace App\Http\Middleware;

use App\Support\Roles;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = auth('api')->user();

        if (! $user) {
            abort(401);
        }

        $allowed = array_map(fn (string $role) => Roles::normalize($role), $roles);

        if (! in_array(Roles::normalize($user->role), $allowed, true)) {
            abort(403);
        }

        return $next($request);
    }
}
