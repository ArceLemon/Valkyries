<?php

namespace App\Http\Controllers;

use App\Kuma\Preuves\Signataire;
use App\Models\Assertion;
use App\Models\Attestation;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vérification publique d'une attestation, sans compte. Elle ne révèle aucune
 * donnée du citoyen : seulement ce que l'attestation affirme.
 */
class PreuveController extends Controller
{
    public function verifier(Request $request, Signataire $signataire): JsonResponse
    {
        $jeton = $request->validate(['jeton' => ['required', 'string', 'max:4000']])['jeton'];

        $resultat = $signataire->verifier(trim($jeton));
        $etat = $resultat['etat'];
        $charge = $resultat['charge'];

        // La signature peut être bonne alors que le citoyen a retiré son consentement.
        if ($etat === Signataire::ETAT_VALIDE && $charge !== null) {
            $revoquee = Attestation::where('reference', $charge['ref'] ?? '')
                ->whereNotNull('revoquee_le')
                ->exists();
            if ($revoquee) {
                $etat = 'revoquee';
            }
        }

        $contenu = null;
        if ($charge !== null && $etat !== Signataire::ETAT_ALTEREE && $etat !== Signataire::ETAT_CLE_INCONNUE) {
            $contenu = [
                'question' => Assertion::where('code', $charge['question'] ?? '')->value('libelle') ?? ($charge['question'] ?? null),
                'parametre' => $charge['parametre'] ?? null,
                'reponse' => $charge['reponse'] ?? null,
                'demandeur' => Organization::where('sigle', $charge['demandeur'] ?? '')->value('nom') ?? ($charge['demandeur'] ?? null),
                'source' => $charge['source'] ?? null,
                'emise_le' => $charge['emise_le'] ?? null,
                'valide_jusqu_au' => $charge['valide_jusqu_au'] ?? null,
                'kid' => $charge['kid'] ?? null,
            ];
        }

        return response()->json(['etat' => $etat, 'attestation' => $contenu]);
    }

    public function cles(Signataire $signataire): JsonResponse
    {
        return response()->json([
            'active' => $signataire->cleActive(),
            'cles' => $signataire->clesPubliques(),
        ]);
    }
}
