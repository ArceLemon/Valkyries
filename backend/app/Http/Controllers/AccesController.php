<?php

namespace App\Http\Controllers;

use App\Kuma\Journal\Journal;
use App\Models\AccessRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Espace du gestionnaire de registre : la source garde la main. Un droit demandé
 * par un organisme ne devient effectif qu'une fois validé par le gestionnaire du
 * registre concerné, qui consigne la référence de la convention et une date limite.
 */
class AccesController extends Controller
{
    /**
     * ?statut=en_attente (par défaut) ou ?statut=valide
     */
    public function index(Request $request): JsonResponse
    {
        $valides = $request->query('statut') === 'valide';

        $droits = $this->droitsDuRegistre($request)
            ->with('organization', 'assertion')
            ->where('valide_par_source', $valides)
            ->latest('id')
            ->get()
            ->map(fn (AccessRule $d) => [
                'id' => $d->id,
                'organisme' => $d->organization->nom,
                'question' => $d->assertion->libelle,
                'registre' => $d->assertion->registre,
                'finalite' => $d->finalite,
                'justification' => $d->justification,
                'etat' => $d->etat(),
                'reference_juridique' => $d->reference_juridique,
                'expire_le' => $d->expire_le?->toIso8601String(),
            ]);

        return response()->json(['donnees' => $droits]);
    }

    public function valider(Request $request, Journal $journal, int $id): JsonResponse
    {
        $donnees = $request->validate([
            'reference_juridique' => ['required', 'string', 'max:200'],
            'expire_le' => ['nullable', 'date', 'after:now'],
        ]);

        $droit = $this->droitsDuRegistre($request)->with('assertion')->findOrFail($id);
        $droit->update([
            'valide_par_source' => true,
            'valide_le' => now(),
            'reference_juridique' => $donnees['reference_juridique'],
            'expire_le' => $donnees['expire_le'] ?? null,
        ]);

        $this->journaliser($request, $journal, 'droit.valide', $droit, [
            'avec_expiration' => $droit->expire_le !== null,
        ]);

        return response()->json(['message' => 'Accès validé.']);
    }

    public function refuser(Request $request, Journal $journal, int $id): JsonResponse
    {
        $droit = $this->droitsDuRegistre($request)->with('assertion')->findOrFail($id);
        $this->journaliser($request, $journal, 'droit.refuse', $droit);
        $droit->delete();

        return response()->json(['message' => 'Accès refusé.']);
    }

    /**
     * Retire un droit déjà validé : la source peut le reprendre à tout moment.
     */
    public function retirer(Request $request, Journal $journal, int $id): JsonResponse
    {
        $droit = $this->droitsDuRegistre($request)->with('assertion')->where('valide_par_source', true)->findOrFail($id);
        $this->journaliser($request, $journal, 'droit.retire', $droit);
        $droit->delete();

        return response()->json(['message' => 'Accès retiré.']);
    }

    /**
     * Seuls les droits portant sur un registre détenu par l'organisme du gestionnaire.
     *
     * @return \Illuminate\Database\Eloquent\Builder<AccessRule>
     */
    private function droitsDuRegistre(Request $request)
    {
        $registres = (array) ($request->user()->organization?->registres ?? []);

        return AccessRule::query()->whereHas(
            'assertion',
            fn ($q) => $q->whereIn('registre', $registres === [] ? ['aucun'] : $registres),
        );
    }

    /**
     * @param  array<string, mixed>  $supplement
     */
    private function journaliser(Request $request, Journal $journal, string $action, AccessRule $droit, array $supplement = []): void
    {
        $journal->ecrire($action, [
            'acteur_type' => $request->user()->role,
            'acteur_id' => $request->user()->id,
            'organization_id' => $droit->organization_id,
            'objet_type' => 'droit',
            'objet_ref' => (string) $droit->id,
            'details' => [
                'question' => $droit->assertion->code,
                'finalite' => $droit->finalite,
                'registre' => $droit->assertion->registre,
            ] + $supplement,
            'resultat' => match ($action) {
                'droit.valide' => 'valide',
                'droit.retire' => 'retire',
                default => 'refuse',
            },
        ]);
    }
}
