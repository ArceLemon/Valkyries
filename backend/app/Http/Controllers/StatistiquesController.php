<?php

namespace App\Http\Controllers;

use App\Kuma\Statut;
use App\Models\Attestation;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\JournalCheckpoint;
use App\Models\Organization;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Indicateurs de pilotage. Uniquement des comptes et des durées : jamais une
 * donnée de citoyen, même pour l'administrateur.
 */
class StatistiquesController extends Controller
{
    private const JOURS = 14;

    /** Indicateurs d'un organisme (responsable) ou d'un agent (ses propres demandes). */
    public function organisme(Request $request): JsonResponse
    {
        $u = $request->user();
        $requete = VerificationRequest::query()
            ->with('assertion:id,libelle,registre')
            ->where('organization_id', $u->organization_id)
            ->where('created_at', '>=', now()->subDays(60)->startOfDay());

        if ($u->role === 'agent') {
            $requete->where('user_id', $u->id);
        }

        return response()->json($this->resume($requete->get()));
    }

    /** Vue de la plateforme : administrateur et auditeur. */
    public function plateforme(): JsonResponse
    {
        $demandes = VerificationRequest::query()
            ->with('assertion:id,libelle,registre')
            ->where('created_at', '>=', now()->subDays(60)->startOfDay())
            ->get();

        $parRegistre = $demandes
            ->groupBy(fn (VerificationRequest $d) => $d->assertion->registre ?? 'inconnu')
            ->map(fn (Collection $l, string $registre) => [
                'registre' => $registre,
                'demandes' => $l->count(),
                'impossibles' => $l->where('reponse', 'impossible')->count(),
            ])
            ->sortByDesc('demandes')
            ->values();

        $consentements = Consent::query()->get()->countBy('statut');
        $attestations = Attestation::query()->get();

        return response()->json($this->resume($demandes) + [
            'par_registre' => $parRegistre,
            'organismes_actifs' => Organization::where('statut', 'actif')->count(),
            'organismes_total' => Organization::count(),
            'comptes_actifs' => User::where('actif', true)->where('role', '!=', 'citoyen')->count(),
            'citoyens' => User::where('role', 'citoyen')->count(),
            'consentements' => [
                'en_attente' => (int) ($consentements['en_attente'] ?? 0),
                'accepte' => (int) ($consentements['accepte'] ?? 0),
                'refuse' => (int) ($consentements['refuse'] ?? 0),
                'retire' => (int) ($consentements['retire'] ?? 0),
            ],
            'attestations' => [
                'emises' => $attestations->count(),
                'valides' => $attestations->filter(fn (Attestation $a) => $a->revoquee_le === null && $a->valide_jusqu_au->isFuture())->count(),
                'revoquees' => $attestations->whereNotNull('revoquee_le')->count(),
            ],
            'journal' => [
                'lignes' => AuditLog::count(),
                'points_de_controle' => JournalCheckpoint::count(),
            ],
        ]);
    }

    /**
     * @param  Collection<int, VerificationRequest>  $demandes
     * @return array<string, mixed>
     */
    private function resume(Collection $demandes): array
    {
        $debutJour = now()->startOfDay();
        $finales = $demandes->filter(fn (VerificationRequest $d) => $d->estFinale());
        $obtenues = $demandes->filter(fn (VerificationRequest $d) => $d->statut === Statut::TERMINEE && in_array($d->reponse, ['oui', 'non'], true));

        $delais = $demandes
            ->filter(fn (VerificationRequest $d) => $d->statut === Statut::TERMINEE && $d->traite_le !== null)
            ->map(fn (VerificationRequest $d) => max(0, $d->created_at->diffInSeconds($d->traite_le, true)))
            ->sort()
            ->values();

        $serie = collect(range(self::JOURS - 1, 0))->map(function (int $i) use ($demandes) {
            $jour = now()->subDays($i)->startOfDay();
            $du = $demandes->filter(fn (VerificationRequest $d) => $d->created_at->isSameDay($jour));

            return [
                'date' => $jour->toDateString(),
                'total' => $du->count(),
                'obtenues' => $du->filter(fn (VerificationRequest $d) => $d->statut === Statut::TERMINEE && in_array($d->reponse, ['oui', 'non'], true))->count(),
            ];
        })->values();

        $precedente = $demandes->filter(fn (VerificationRequest $d) => $d->created_at->between(now()->subDays(14)->startOfDay(), now()->subDays(7)->startOfDay()))->count();
        $semaine = $demandes->filter(fn (VerificationRequest $d) => $d->created_at->gte(now()->subDays(7)->startOfDay()))->count();

        return [
            'total' => $demandes->count(),
            'aujourdhui' => $demandes->filter(fn (VerificationRequest $d) => $d->created_at->gte($debutJour))->count(),
            'semaine' => $semaine,
            'semaine_precedente' => $precedente,
            'papiers_evites' => $obtenues->count(),
            'taux_reponse' => $finales->isEmpty() ? null : (int) round($obtenues->count() / $finales->count() * 100),
            'delai_median_secondes' => $delais->isEmpty() ? null : (int) $delais[intdiv($delais->count(), 2)],
            'attente_citoyen' => $demandes->where('statut', Statut::EN_ATTENTE_CONSENTEMENT)->count(),
            'attente_registre' => $demandes->where('statut', Statut::EN_ATTENTE_REGISTRE)->count(),
            'repartition' => [
                'oui' => $demandes->where('statut', Statut::TERMINEE)->where('reponse', 'oui')->count(),
                'non' => $demandes->where('statut', Statut::TERMINEE)->where('reponse', 'non')->count(),
                'impossible' => $demandes->where('reponse', 'impossible')->count(),
                'refusee' => $demandes->where('statut', Statut::REFUSEE_CITOYEN)->count(),
                'expiree' => $demandes->where('statut', Statut::EXPIREE)->count(),
                'en_cours' => $demandes->reject(fn (VerificationRequest $d) => $d->estFinale())->count(),
            ],
            'serie' => $serie,
            'top_questions' => $demandes
                ->groupBy('assertion_id')
                ->map(fn (Collection $l) => ['libelle' => $l->first()->assertion->libelle, 'demandes' => $l->count()])
                ->sortByDesc('demandes')
                ->take(5)
                ->values(),
        ];
    }
}
