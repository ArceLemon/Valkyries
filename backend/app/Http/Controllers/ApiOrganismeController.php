<?php

namespace App\Http\Controllers;

use App\Kuma\Demandes\ServiceDemandes;
use App\Models\VerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Point d'entrée du logiciel d'une administration (clé d'API). Les droits de
 * l'organisme, le consentement et le journal s'appliquent exactement comme au portail.
 */
class ApiOrganismeController extends Controller
{
    public function store(Request $request, ServiceDemandes $service): JsonResponse
    {
        $donnees = $request->validate(DemandeController::regles());

        $cle = $request->attributes->get('cle_api');
        if (! $cle->autorise($donnees['question'])) {
            abort(403, 'Cette clé n\'est pas autorisée pour cette question.');
        }

        $demande = $service->creer(
            $request->user(),
            $donnees,
            (array) $request->attributes->get('trace_api', []),
        );

        return response()->json($demande->vue(), 201);
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        // Une clé ne lit que les demandes qu'elle a elle-même posées
        $demande = VerificationRequest::query()
            ->where('user_id', $request->user()->id)
            ->where('reference', $reference)
            ->firstOrFail();

        return response()->json($demande->vue());
    }
}
