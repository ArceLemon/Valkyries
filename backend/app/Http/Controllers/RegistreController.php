<?php

namespace App\Http\Controllers;

use App\Kuma\Registres\GestionnaireRegistres;
use Illuminate\Http\JsonResponse;

class RegistreController extends Controller
{
    /**
     * État en direct des registres : en service, lent ou en panne.
     */
    public function etat(GestionnaireRegistres $registres): JsonResponse
    {
        $etat = collect($registres->registres())->map(function (string $nom) use ($registres) {
            $sante = $registres->sante($nom);

            return [
                'registre' => $nom,
                'etat' => $sante->etat,
                'latence_ms' => $sante->latence_ms,
                'verifie_le' => $sante->verifie_le?->toIso8601String(),
                'disjoncteur_ouvert' => $sante->disjoncteurOuvert(),
            ];
        });

        return response()->json(['donnees' => $etat->values()]);
    }
}
