<?php

namespace App\Http\Controllers;

use App\Kuma\Api\GestionCles;
use App\Models\ApiCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Espace du responsable d'organisme : les clés d'API de son organisme.
 */
class CleApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cles = ApiCredential::query()
            ->where('organization_id', $request->user()->organization_id)
            ->latest('id')
            ->get()
            ->map(fn (ApiCredential $c) => $this->vue($c));

        return response()->json(['donnees' => $cles]);
    }

    /**
     * La clé complète n'est renvoyée qu'ici, une seule fois.
     */
    public function creer(Request $request, GestionCles $cles): JsonResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:120'],
            'questions' => ['nullable', 'array', 'min:1'],
            'questions.*' => ['string', 'exists:assertions,code'],
            'quota_par_minute' => ['nullable', 'integer', 'min:1', 'max:600'],
            'expire_le' => ['nullable', 'date', 'after:now'],
            'cle_publique' => ['nullable', 'string', function (string $attribut, mixed $valeur, \Closure $echec) {
                $brut = base64_decode((string) $valeur, true);
                if ($brut === false || strlen($brut) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                    $echec('La clé publique doit être une clé Ed25519 de 32 octets encodée en base64.');
                }
            }],
        ]);

        $organisme = $request->user()->organization;
        if (! $organisme->aRole('demandeuse')) {
            abort(403, 'Seule une administration demandeuse peut avoir des clés d\'API.');
        }

        $resultat = $cles->creer($organisme, $request->user(), $donnees);

        return response()->json($this->vue($resultat['credential']) + [
            'cle' => $resultat['cle'],
            'avertissement' => 'Conservez cette clé maintenant : elle ne sera plus jamais affichée.',
        ], 201);
    }

    public function revoquer(Request $request, GestionCles $cles, int $id): JsonResponse
    {
        $credential = ApiCredential::query()
            ->where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        $cles->revoquer($credential, $request->user());

        return response()->json(['message' => 'Clé révoquée.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function vue(ApiCredential $c): array
    {
        return [
            'id' => $c->id,
            'nom' => $c->nom,
            'identifiant' => $c->kid,
            'questions' => $c->questions,
            'quota_par_minute' => $c->quota_par_minute,
            'signature_obligatoire' => $c->signatureObligatoire(),
            'expire_le' => $c->expire_le?->toIso8601String(),
            'revoquee' => $c->revoquee_le !== null,
            'dernier_usage_le' => $c->dernier_usage_le?->toIso8601String(),
        ];
    }
}
