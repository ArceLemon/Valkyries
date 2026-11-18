<?php

namespace App\Http\Controllers;

use App\Kuma\Journal\Journal;
use App\Models\AccessRule;
use App\Models\Assertion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Espace du responsable d'organisme : les droits de son organisme et la demande
 * d'habilitation auprès de la source.
 */
class DroitsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $droits = AccessRule::query()
            ->with('assertion')
            ->where('organization_id', $request->user()->organization_id)
            ->latest('id')
            ->get()
            ->map(fn (AccessRule $d) => [
                'id' => $d->id,
                'question' => $d->assertion->code,
                'libelle' => $d->assertion->libelle,
                'finalite' => $d->finalite,
                'etat' => $d->etat(),
                'justification' => $d->justification,
                'reference_juridique' => $d->reference_juridique,
                'expire_le' => $d->expire_le?->toIso8601String(),
            ]);

        return response()->json(['donnees' => $droits]);
    }

    /**
     * Les questions que l'organisme peut réellement poser aujourd'hui (droits validés,
     * non expirés, question publiée). Sert à construire le formulaire de demande.
     */
    public function disponibles(Request $request): JsonResponse
    {
        $droits = AccessRule::query()
            ->with('assertion')
            ->where('organization_id', $request->user()->organization_id)
            ->where('valide_par_source', true)
            ->where(fn ($q) => $q->whereNull('expire_le')->orWhere('expire_le', '>', now()))
            ->whereHas('assertion', fn ($q) => $q->where('publie', true))
            ->get()
            ->map(fn (AccessRule $d) => [
                'question' => $d->assertion->code,
                'libelle' => $d->assertion->libelle,
                'description' => $d->assertion->description,
                'finalite' => $d->finalite,
                'regime' => $d->assertion->regime,
                'sensibilite' => $d->assertion->sensibilite,
                'parametree' => $d->assertion->parametree,
            ])
            ->sortBy(['finalite', 'libelle'])
            ->values();

        return response()->json(['donnees' => $droits]);
    }

    /**
     * Demande un droit : il reste sans effet tant que la source ne l'a pas validé.
     */
    public function demander(Request $request, Journal $journal): JsonResponse
    {
        $donnees = $request->validate([
            'question' => ['required', 'string', 'exists:assertions,code'],
            'finalite' => ['required', 'string', 'max:200'],
            'justification' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $organisme = $request->user()->organization;
        if (! $organisme->aRole('demandeuse')) {
            abort(403, 'Seule une administration demandeuse peut demander un accès.');
        }

        $assertion = Assertion::where('code', $donnees['question'])->where('publie', true)->first();
        if ($assertion === null) {
            abort(422, 'Cette question n\'est pas publiée au catalogue.');
        }

        $existe = AccessRule::where('organization_id', $organisme->id)
            ->where('assertion_id', $assertion->id)
            ->where('finalite', $donnees['finalite'])
            ->exists();
        if ($existe) {
            abort(409, 'Une demande ou un droit identique existe déjà.');
        }

        $droit = AccessRule::create([
            'organization_id' => $organisme->id,
            'assertion_id' => $assertion->id,
            'finalite' => $donnees['finalite'],
            'justification' => $donnees['justification'],
            'valide_par_source' => false,
        ]);

        $journal->ecrire('droit.demande', [
            'acteur_type' => $request->user()->role,
            'acteur_id' => $request->user()->id,
            'organization_id' => $organisme->id,
            'objet_type' => 'droit',
            'objet_ref' => (string) $droit->id,
            'details' => ['question' => $assertion->code, 'finalite' => $donnees['finalite']],
            'resultat' => 'en_attente_validation',
        ]);

        return response()->json(['id' => $droit->id, 'etat' => $droit->etat()], 201);
    }
}
