<?php

namespace App\Http\Controllers;

use App\Kuma\Comptes\Revocation;
use App\Kuma\Journal\Journal;
use App\Kuma\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Espace du responsable d'organisme : son équipe d'agents.
 */
class EquipeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $agents = User::where('organization_id', $request->user()->organization_id)
            ->where('technique', false)
            ->whereIn('role', [Role::Agent->value, Role::Responsable->value])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => $this->vue($u));

        return response()->json(['donnees' => $agents]);
    }

    public function creer(Request $request, Journal $journal): JsonResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'mot_de_passe' => ['required', 'string', 'min:12'],
        ]);

        $agent = User::create([
            'name' => $donnees['nom'],
            'email' => $donnees['email'],
            'password' => $donnees['mot_de_passe'],
            'role' => Role::Agent->value,
            'organization_id' => $request->user()->organization_id,
            'actif' => true,
        ]);

        $journal->ecrire('compte.cree', [
            'acteur_type' => $request->user()->role,
            'acteur_id' => $request->user()->id,
            'organization_id' => $agent->organization_id,
            'objet_type' => 'utilisateur',
            'objet_ref' => (string) $agent->id,
            'details' => ['role_cible' => $agent->role],
            'resultat' => 'cree',
        ]);

        return response()->json($this->vue($agent), 201);
    }

    /**
     * Révocation immédiate : l'agent perd l'accès à l'instant, sur tous ses appareils.
     */
    public function revoquer(Request $request, Revocation $revocation, int $id): JsonResponse
    {
        $agent = User::where('organization_id', $request->user()->organization_id)
            ->where('role', Role::Agent->value)
            ->findOrFail($id);

        $revocation->revoquerUtilisateur($agent, $request->user());

        return response()->json(['message' => 'Agent révoqué.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function vue(User $u): array
    {
        return [
            'id' => $u->id,
            'nom' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'actif' => $u->actif,
        ];
    }
}
