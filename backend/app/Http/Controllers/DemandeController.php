<?php

namespace App\Http\Controllers;

use App\Kuma\Demandes\ServiceDemandes;
use App\Models\VerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemandeController extends Controller
{
    /**
     * Règles de validation d'une demande, partagées par le portail et l'API.
     *
     * @return array<string, list<string>>
     */
    public static function regles(): array
    {
        return [
            'question' => ['required', 'string', 'max:100'],
            'parametre' => ['nullable', 'string', 'max:50'],
            'finalite' => ['required', 'string', 'max:200'],
            'citoyen' => ['required', 'array'],
            // L'identifiant unique suffit : aucune autre donnée personnelle n'est demandée.
            'citoyen.identifiant' => ['required', 'string', 'max:64'],
        ];
    }

    public function store(Request $request, ServiceDemandes $service): JsonResponse
    {
        $donnees = $request->validate(self::regles());

        $demande = $service->creer($request->user(), $donnees);

        return response()->json($demande->vue(), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $demandes = $this->portee($request)
            ->with(['assertion', 'attestation', 'consentement'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (VerificationRequest $d) => $d->vue());

        return response()->json(['donnees' => $demandes]);
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        $demande = $this->portee($request)->where('reference', $reference)->firstOrFail();

        return response()->json($demande->vue());
    }

    /**
     * Accord donné par code SMS, pour le citoyen qui n'a pas l'application.
     */
    public function code(Request $request, ServiceDemandes $service, string $reference): JsonResponse
    {
        $donnees = $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']]);
        $demande = $this->portee($request)->where('reference', $reference)->firstOrFail();

        return response()->json($service->accepterParCode($request->user(), $demande, $donnees['code'])->vue());
    }

    /**
     * Un agent voit ses demandes, un responsable celles de son organisme.
     * Personne ne voit les demandes d'un autre organisme.
     *
     * @return \Illuminate\Database\Eloquent\Builder<VerificationRequest>
     */
    private function portee(Request $request)
    {
        $utilisateur = $request->user();
        $requete = VerificationRequest::query()->where('organization_id', $utilisateur->organization_id);

        if ($utilisateur->role === 'agent') {
            $requete->where('user_id', $utilisateur->id);
        }

        return $requete;
    }
}
