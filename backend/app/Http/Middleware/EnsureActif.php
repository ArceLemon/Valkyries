<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Révocation immédiate : à chaque requête, on vérifie que le compte est toujours
 * actif et que son organisme n'est pas suspendu. Un agent révoqué perd donc
 * l'accès à la requête suivante, même si son jeton existait encore.
 */
class EnsureActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->user();

        if ($utilisateur === null || ! $utilisateur->peutSeConnecter()) {
            $jeton = $utilisateur?->currentAccessToken();
            if ($jeton instanceof PersonalAccessToken) {
                $jeton->delete();
            }

            abort(403, 'Compte désactivé ou organisme suspendu.');
        }

        return $next($request);
    }
}
