<?php

namespace App\Http\Middleware;

use App\Support\AccessControl;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /** Déconnecte immédiatement un compte désactivé ou qui n'a plus aucun accès. */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->is_active || AccessControl::homeRouteFor($user) === null)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', $user->is_active
                ? 'Votre compte n\'a accès à aucun espace. Contactez un administrateur.'
                : 'Votre compte est désactivé. Contactez un administrateur.');
        }

        return $next($request);
    }
}
