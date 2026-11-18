<?php

namespace App\Http\Middleware;

use App\Models\ApiCredential;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentifie le logiciel d'une administration par sa clé d'API (en-tête X-Api-Key).
 *
 * Si l'organisme a enregistré une clé publique Ed25519, chaque demande doit en plus
 * être SIGNÉE. La signature couvre l'heure, un nonce, la méthode, le chemin et
 * l'empreinte du corps. Elle prouve qui a demandé et empêche le rejeu.
 *
 *   X-Kuma-Horodatage : secondes Unix
 *   X-Kuma-Nonce      : valeur unique par requête (16 à 64 caractères)
 *   X-Kuma-Signature  : base64 de la signature Ed25519 du message
 *
 * Message signé : horodatage \n nonce \n METHODE \n chemin \n sha256(corps) en hexadécimal
 */
class AuthentifierCleApi
{
    /** Écart d'horloge toléré, en secondes. */
    private const TOLERANCE = 300;

    public function handle(Request $request, Closure $next): Response
    {
        // Frein contre les essais de clés au hasard
        $cleEchecs = 'api-echecs:'.$request->ip();
        if (RateLimiter::tooManyAttempts($cleEchecs, 20)) {
            abort(429, 'Trop de clés invalides. Réessayez dans une minute.');
        }

        $credential = $this->identifier($request);
        if ($credential === null) {
            RateLimiter::hit($cleEchecs, 60);
            abort(401, 'Clé d\'API absente ou invalide.');
        }

        if (! $credential->estUtilisable()) {
            abort(401, $credential->revoquee_le !== null ? 'Clé révoquée.' : 'Clé expirée.');
        }

        $technique = $credential->technique;
        if ($technique === null || ! $technique->peutSeConnecter()) {
            abort(403, 'Compte désactivé ou organisme suspendu.');
        }

        $cleQuota = 'api-cle:'.$credential->kid;
        if (RateLimiter::tooManyAttempts($cleQuota, $credential->quota_par_minute ?? 60)) {
            abort(429, 'Quota de demandes dépassé pour cette clé.');
        }
        RateLimiter::hit($cleQuota, 60);

        $trace = ['canal' => 'api', 'cle' => $credential->kid];
        if ($credential->signatureObligatoire()) {
            $trace['signature'] = $this->verifierSignature($request, $credential);
        }

        $credential->forceFill(['dernier_usage_le' => now()])->save();

        $request->setUserResolver(fn () => $technique);
        $request->attributes->set('cle_api', $credential);
        $request->attributes->set('trace_api', $trace);

        return $next($request);
    }

    private function identifier(Request $request): ?ApiCredential
    {
        $brute = (string) $request->header('X-Api-Key', '');
        if (! preg_match('/^kb_([a-f0-9]{12})_([a-f0-9]{40})$/', $brute, $m)) {
            return null;
        }

        $credential = ApiCredential::with('technique.organization')->where('kid', $m[1])->first();
        if ($credential === null || ! hash_equals($credential->secret_hash, hash('sha256', $m[2]))) {
            return null;
        }

        return $credential;
    }

    private function verifierSignature(Request $request, ApiCredential $credential): string
    {
        $horodatage = (string) $request->header('X-Kuma-Horodatage', '');
        $nonce = (string) $request->header('X-Kuma-Nonce', '');
        $signature = (string) $request->header('X-Kuma-Signature', '');

        if ($horodatage === '' || $nonce === '' || $signature === '') {
            abort(401, 'Cette clé exige une demande signée (X-Kuma-Horodatage, X-Kuma-Nonce, X-Kuma-Signature).');
        }
        if (! ctype_digit($horodatage) || abs(time() - (int) $horodatage) > self::TOLERANCE) {
            abort(401, 'Horodatage de la demande hors tolérance.');
        }
        if (! preg_match('/^[A-Za-z0-9._-]{16,64}$/', $nonce)) {
            abort(401, 'Nonce invalide.');
        }

        $message = implode("\n", [
            $horodatage,
            $nonce,
            strtoupper($request->method()),
            $request->getPathInfo(),
            hash('sha256', $request->getContent()),
        ]);

        $brute = base64_decode($signature, true);
        $publique = base64_decode((string) $credential->cle_publique, true);

        $valide = $brute !== false && $publique !== false
            && strlen($brute) === SODIUM_CRYPTO_SIGN_BYTES
            && strlen($publique) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            && sodium_crypto_sign_verify_detached($brute, $message, $publique);

        if (! $valide) {
            abort(401, 'Signature de la demande invalide.');
        }

        // Anti-rejeu : on ne consomme le nonce qu'une fois la signature vérifiée,
        // pour qu'un tiers ne puisse pas « brûler » les nonces d'autrui.
        if (! Cache::add('api-nonce:'.$credential->kid.':'.$nonce, 1, self::TOLERANCE * 2)) {
            abort(401, 'Demande déjà reçue (rejeu refusé).');
        }

        return $signature;
    }
}
