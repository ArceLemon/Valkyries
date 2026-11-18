<?php

namespace App\Http\Controllers;

use App\Kuma\Demandes\ServiceDemandes;
use App\Models\Procedure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DemarcheController extends Controller
{
    public function index(): JsonResponse
    {
        $demarches = Procedure::with('assertions')->orderBy('libelle')->get()->map(fn (Procedure $p) => [
            'code' => $p->code,
            'libelle' => $p->libelle,
            'finalite' => $p->finalite,
            'questions' => $p->assertions->map(fn ($a) => [
                'question' => $a->code,
                'libelle' => $a->libelle,
                'parametre' => $a->pivot->parametre,
            ])->values(),
        ]);

        return response()->json(['donnees' => $demarches]);
    }

    /**
     * Lance toutes les questions d'une démarche pour un même citoyen.
     * Chaque question est traitée séparément : un refus de droit sur l'une
     * n'empêche pas les autres.
     */
    public function lancer(Request $request, ServiceDemandes $service, string $code): JsonResponse
    {
        $demarche = Procedure::with('assertions')->where('code', $code)->firstOrFail();

        $citoyen = $request->validate([
            'citoyen' => ['required', 'array'],
            'citoyen.identifiant' => ['required', 'string', 'max:64'],
        ])['citoyen'];
        $citoyen = ['identifiant' => $citoyen['identifiant']];

        $lot = (string) Str::ulid();
        $resultats = [];
        $piecesEvitees = 0;

        foreach ($demarche->assertions as $assertion) {
            try {
                $demande = $service->creer($request->user(), [
                    'question' => $assertion->code,
                    'parametre' => $assertion->pivot->parametre,
                    'finalite' => $demarche->finalite,
                    'citoyen' => $citoyen,
                    'lot' => $lot,
                ]);
                $vue = $demande->vue();
                $resultats[] = $vue;
                if ($demande->statut === 'terminee' && in_array($demande->reponse, ['oui', 'non'], true)) {
                    $piecesEvitees++;
                }
            } catch (HttpException $e) {
                $resultats[] = [
                    'question' => $assertion->code,
                    'libelle' => $assertion->libelle,
                    'statut' => 'rejetee',
                    'erreur' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'lot' => $lot,
            'demarche' => $demarche->code,
            'demandes' => $resultats,
            'pieces_evitees' => $piecesEvitees,
        ], 201);
    }
}
