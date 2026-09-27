<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /** Redirige chaque utilisateur vers son propre espace s'il n'a pas le rôle demandé. */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user->hasRole(Role::from($role))) {
            return redirect()->route($user->role->homeRoute());
        }

        return $next($request);
    }
}
