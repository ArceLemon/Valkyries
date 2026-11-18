<?php

namespace App\Http\Controllers;

use App\Models\Assertion;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

/**
 * Catalogue public des questions auxquelles KUMA BANA sait répondre : ce qu'une
 * administration peut demander, auprès de quelle source, sous quel régime.
 * Aucune donnée de citoyen, aucune règle de calcul interne.
 */
class CatalogueController extends Controller
{
    public function index(): JsonResponse
    {
        $sources = $this->sources();

        $questions = Assertion::query()
            ->where('publie', true)
            ->orderBy('code')
            ->get()
            ->map(fn (Assertion $a) => $this->vue($a, $sources));

        return response()->json(['donnees' => $questions]);
    }

    public function show(string $code): JsonResponse
    {
        $assertion = Assertion::where('publie', true)->where('code', $code)->firstOrFail();

        return response()->json($this->vue($assertion, $this->sources()));
    }

    /**
     * Registre => nom de l'organisme qui le détient.
     *
     * @return array<string, string>
     */
    private function sources(): array
    {
        $sources = [];
        foreach (Organization::query()->whereNotNull('registres')->get() as $organisme) {
            foreach ((array) $organisme->registres as $registre) {
                $sources[$registre] = $organisme->nom;
            }
        }

        return $sources;
    }

    /**
     * @param  array<string, string>  $sources
     * @return array<string, mixed>
     */
    private function vue(Assertion $a, array $sources): array
    {
        return [
            'code' => $a->code,
            'libelle' => $a->libelle,
            'description' => $a->description,
            'source' => $sources[$a->registre] ?? null,
            'registre' => $a->registre,
            'sensibilite' => $a->sensibilite,
            'regime' => $a->regime,
            'parametree' => $a->parametree,
            'validite_minutes' => $a->validite_minutes,
            'reponses' => ['oui', 'non', 'impossible'],
            'base_juridique' => $a->base_juridique,
        ];
    }
}
