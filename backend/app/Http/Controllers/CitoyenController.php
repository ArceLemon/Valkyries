<?php

namespace App\Http\Controllers;

use App\Kuma\Demandes\ServiceDemandes;
use App\Kuma\Notifications\Push;
use App\Kuma\Support\Pseudonymiseur;
use App\Models\Assertion;
use App\Models\Attestation;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\Organization;
use App\Models\SmsEnvoye;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Espace du citoyen : demandes à valider, consentements, et surtout
 * « qui a consulté mes données ».
 */
class CitoyenController extends Controller
{
    private const ACTIONS_VISIBLES = [
        'donnee.consultee', 'consentement.accepte', 'consentement.refuse', 'consentement.retire',
    ];

    /**
     * Abonnement aux notifications push : le sujet ntfy secret du citoyen, et le
     * chemin où l'application s'abonne (en lecture seule, via le portail).
     */
    public function abonnement(Request $request, Push $push): JsonResponse
    {
        if (! $push->actif()) {
            return response()->json(['actif' => false, 'sujet' => null, 'chemin' => null]);
        }

        $sujet = $push->sujet($request->user());

        return response()->json([
            'actif' => true,
            'sujet' => $sujet,
            'chemin' => rtrim((string) config('kuma.push.chemin_public'), '/').'/'.$sujet.'/json',
        ]);
    }

    public function aValider(Request $request, Pseudonymiseur $pseudonymes): JsonResponse
    {
        $consentements = Consent::query()
            ->with('demande.assertion', 'demande.organization')
            ->where('sujet_pseudo', $this->sujet($request, $pseudonymes))
            ->where('statut', 'en_attente')
            ->where('expire_le', '>', now())
            ->latest('id')
            ->get()
            ->map(fn (Consent $c) => [
                'id' => $c->id,
                'organisme' => $c->demande->organization->nom,
                'question' => $c->demande->assertion->libelle,
                'finalite' => $c->demande->finalite,
                'sensibilite' => $c->demande->assertion->sensibilite,
                'expire_le' => $c->expire_le->toIso8601String(),
            ]);

        return response()->json(['donnees' => $consentements]);
    }

    public function accepter(Request $request, Pseudonymiseur $pseudonymes, ServiceDemandes $service, int $id): JsonResponse
    {
        $consentement = $this->consentementDuCitoyen($request, $pseudonymes, $id);
        $service->decider($request->user(), $consentement, true);

        return response()->json(['message' => 'Consentement enregistré.']);
    }

    public function refuser(Request $request, Pseudonymiseur $pseudonymes, ServiceDemandes $service, int $id): JsonResponse
    {
        $consentement = $this->consentementDuCitoyen($request, $pseudonymes, $id);
        $service->decider($request->user(), $consentement, false);

        return response()->json(['message' => 'Demande refusée.']);
    }

    public function retirer(Request $request, Pseudonymiseur $pseudonymes, ServiceDemandes $service, int $id): JsonResponse
    {
        $consentement = $this->consentementDuCitoyen($request, $pseudonymes, $id);
        $service->retirer($request->user(), $consentement);

        return response()->json(['message' => 'Consentement retiré.']);
    }

    /**
     * Messages envoyés au citoyen pour l'avertir qu'un accord l'attend.
     */
    public function messages(Request $request): JsonResponse
    {
        $messages = SmsEnvoye::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (SmsEnvoye $m) => [
                'id' => $m->id,
                'canal' => $m->canal,
                'message' => $m->message,
                'envoye_le' => $m->created_at->toIso8601String(),
            ]);

        return response()->json(['donnees' => $messages]);
    }

    public function consentements(Request $request, Pseudonymiseur $pseudonymes): JsonResponse
    {
        $consentements = Consent::query()
            ->with('demande.assertion', 'demande.organization')
            ->where('sujet_pseudo', $this->sujet($request, $pseudonymes))
            ->whereIn('statut', ['accepte', 'retire', 'refuse', 'expire'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (Consent $c) => [
                'id' => $c->id,
                'statut' => $c->statut,
                'organisme' => $c->demande->organization->nom,
                'question' => $c->demande->assertion->libelle,
                'finalite' => $c->demande->finalite,
                'decide_le' => $c->decide_le?->toIso8601String(),
            ]);

        return response()->json(['donnees' => $consentements]);
    }

    /**
     * Qui a consulté mes données : organisme, question, date, finalité, réponse.
     */
    public function historique(Request $request, Pseudonymiseur $pseudonymes): JsonResponse
    {
        $lignes = AuditLog::query()
            ->where('sujet_pseudo', $this->sujet($request, $pseudonymes))
            ->whereIn('action', self::ACTIONS_VISIBLES)
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $organismes = Organization::whereIn('id', $lignes->pluck('organization_id')->filter()->unique())
            ->pluck('nom', 'id');
        $questions = Assertion::pluck('libelle', 'code');

        $donnees = $lignes->map(fn (AuditLog $l) => [
            'date' => $l->horodatage,
            'type' => $l->action,
            'organisme' => $organismes[$l->organization_id] ?? null,
            'question' => $questions[$l->details['question'] ?? ''] ?? ($l->details['question'] ?? null),
            'finalite' => $l->details['finalite'] ?? null,
            'regime' => $l->details['regime'] ?? null,
            'reponse' => $l->details['reponse'] ?? null,
        ]);

        return response()->json(['donnees' => $donnees]);
    }

    public function preuves(Request $request, Pseudonymiseur $pseudonymes): JsonResponse
    {
        $preuves = Attestation::query()
            ->with('demande.assertion', 'demande.organization')
            ->whereHas('demande', fn ($q) => $q->where('sujet_pseudo', $this->sujet($request, $pseudonymes)))
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (Attestation $a) => [
                'reference' => $a->reference,
                'question' => $a->demande->assertion->libelle,
                'organisme' => $a->demande->organization->nom,
                'valide_jusqu_au' => $a->valide_jusqu_au->toIso8601String(),
                'revoquee' => $a->revoquee_le !== null,
                'jeton' => $a->jeton,
            ]);

        return response()->json(['donnees' => $preuves]);
    }

    private function sujet(Request $request, Pseudonymiseur $pseudonymes): string
    {
        $identifiant = $request->user()->identifiant;

        // Un compte sans identifiant ne correspond à personne : on renvoie une
        // empreinte qui ne peut rien retrouver.
        return $identifiant === null ? 'aucun' : $pseudonymes->pour($identifiant);
    }

    private function consentementDuCitoyen(Request $request, Pseudonymiseur $pseudonymes, int $id): Consent
    {
        return Consent::with('demande.assertion')
            ->where('id', $id)
            ->where('sujet_pseudo', $this->sujet($request, $pseudonymes))
            ->firstOrFail();
    }
}
