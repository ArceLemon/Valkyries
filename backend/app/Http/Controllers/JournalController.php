<?php

namespace App\Http\Controllers;

use App\Kuma\Journal\Journal;
use App\Models\AuditLog;
use App\Models\JournalCheckpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    /**
     * Journal global (administrateur, auditeur). Les citoyens y figurent
     * seulement sous forme d'empreinte tronquée.
     */
    public function index(Request $request): JsonResponse
    {
        $requete = AuditLog::query()->orderByDesc('id');

        if ($request->filled('action')) {
            $requete->where('action', $request->string('action'));
        }
        if ($request->filled('organisme')) {
            $requete->where('organization_id', $request->integer('organisme'));
        }

        return response()->json(['donnees' => $requete->limit(200)->get()->map(fn (AuditLog $l) => $this->vue($l))]);
    }

    /**
     * Journal d'un organisme (responsable) : ce que ses agents ont fait.
     */
    public function organisme(Request $request): JsonResponse
    {
        $lignes = AuditLog::query()
            ->where('organization_id', $request->user()->organization_id)
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (AuditLog $l) => $this->vue($l));

        return response()->json(['donnees' => $lignes]);
    }

    public function verifier(Journal $journal): JsonResponse
    {
        return response()->json($journal->verifier());
    }

    /**
     * Points de contrôle signés : publics, car ils ne contiennent qu'une empreinte
     * et une signature. Quiconque en garde une copie peut détecter une réécriture.
     */
    public function pointsDeControle(): JsonResponse
    {
        $points = JournalCheckpoint::query()->orderByDesc('id')->limit(50)->get()->map(fn (JournalCheckpoint $p) => [
            'id' => $p->id,
            'dernier_id' => $p->dernier_id,
            'total' => $p->total,
            'hash' => $p->hash,
            'kid' => $p->kid,
            'jeton' => $p->jeton,
            'cree_le' => $p->created_at?->toIso8601String(),
        ]);

        return response()->json(['donnees' => $points]);
    }

    public function signerPointDeControle(Journal $journal): JsonResponse
    {
        $point = $journal->creerPointDeControle();

        return response()->json([
            'cree' => $point !== null,
            'dernier_id' => $point?->dernier_id,
        ], $point === null ? 200 : 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function vue(AuditLog $l): array
    {
        return [
            'id' => $l->id,
            'date' => $l->horodatage,
            'acteur_type' => $l->acteur_type,
            'acteur_id' => $l->acteur_id === null ? null : (string) $l->acteur_id,
            'organisme' => $l->organization_id,
            'action' => $l->action,
            'objet' => $l->objet_ref,
            'sujet' => $l->sujet_pseudo === null ? null : substr($l->sujet_pseudo, 0, 12),
            'details' => $l->details,
            'resultat' => $l->resultat,
            'hash' => $l->hash,
        ];
    }
}
