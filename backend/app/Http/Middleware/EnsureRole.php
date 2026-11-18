<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limite une route à un ou plusieurs rôles : ->middleware('role:agent,responsable')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $utilisateur = $request->user();

        if ($utilisateur === null || ! in_array($utilisateur->role, $roles, true)) {
            abort(403, 'Accès refusé pour votre rôle.');
        }

        return $next($request);
    }
}
