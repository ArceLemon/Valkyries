<?php

namespace App\Http\Controllers;

use App\Kuma\Comptes\Inscription;
use App\Kuma\Journal\Journal;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function connexion(Request $request, Journal $journal): JsonResponse
    {
        // Les agents se connectent par e-mail, les citoyens par leur identifiant (BF-0001).
        $donnees = $request->validate([
            'email' => ['required_without:identifiant', 'nullable', 'email'],
            'identifiant' => ['required_without:email', 'nullable', 'string', 'max:64'],
            'mot_de_passe' => ['required', 'string'],
        ]);
        $parIdentifiant = ! empty($donnees['identifiant']);
        $login = $parIdentifiant ? mb_strtoupper(trim($donnees['identifiant'])) : Str::lower(trim($donnees['email']));

        $cle = 'connexion:'.Str::lower($login).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($cle, (int) config('kuma.connexion.tentatives_par_minute'))) {
            abort(429, 'Trop de tentatives. Réessayez dans une minute.');
        }

        $utilisateur = $parIdentifiant
            ? User::where('identifiant', $login)->first()
            : User::whereRaw('lower(email) = ?', [$login])->first();

        // Un compte technique (clé d'API) ne se connecte jamais au portail.
        if ($utilisateur === null || $utilisateur->technique || ! Hash::check($donnees['mot_de_passe'], $utilisateur->password)) {
            RateLimiter::hit($cle, 60);
            $journal->ecrire('auth.echec', [
                'acteur_type' => 'inconnu',
                'details' => ['cause' => 'identifiants_incorrects'],
                'resultat' => 'echec',
            ]);
            abort(401, 'Identifiant ou mot de passe incorrect.');
        }

        if (! $utilisateur->peutSeConnecter()) {
            $journal->ecrire('auth.echec', [
                'acteur_type' => $utilisateur->role,
                'acteur_id' => $utilisateur->id,
                'organization_id' => $utilisateur->organization_id,
                'details' => ['cause' => 'compte_desactive'],
                'resultat' => 'echec',
            ]);
            abort(403, 'Compte désactivé ou organisme suspendu.');
        }

        RateLimiter::clear($cle);

        $jeton = $utilisateur->createToken(
            'portail',
            [$utilisateur->role],
            now()->addHours((int) config('kuma.connexion.jeton_validite_heures')),
        )->plainTextToken;

        $journal->ecrire('auth.connexion', [
            'acteur_type' => $utilisateur->role,
            'acteur_id' => $utilisateur->id,
            'organization_id' => $utilisateur->organization_id,
            'resultat' => 'succes',
        ]);

        return response()->json([
            'jeton' => $jeton,
            'utilisateur' => $this->vue($utilisateur),
        ]);
    }

    /**
     * Un citoyen crée son compte depuis l'application. Son identité est vérifiée
     * auprès du registre d'état civil ; il est ensuite connecté directement.
     */
    public function inscription(Request $request, Inscription $inscription): JsonResponse
    {
        $donnees = $request->validate([
            'identifiant' => ['required', 'string', 'max:64'],
            'nom' => ['required', 'string', 'max:100'],
            'prenoms' => ['required', 'string', 'max:150'],
            'telephone' => ['required', 'string', 'regex:/^\+?[0-9 ]{8,20}$/'],
            'mot_de_passe' => ['required', 'string', 'min:8', 'max:200'],
        ]);

        $citoyen = $inscription->inscrire($donnees);

        $jeton = $citoyen->createToken(
            'application',
            [$citoyen->role],
            now()->addHours((int) config('kuma.connexion.jeton_validite_heures')),
        )->plainTextToken;

        return response()->json([
            'jeton' => $jeton,
            'utilisateur' => $this->vue($citoyen),
        ], 201);
    }

    public function moi(Request $request): JsonResponse
    {
        return response()->json($this->vue($request->user()));
    }

    public function deconnexion(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function vue(User $utilisateur): array
    {
        $utilisateur->loadMissing('organization');

        return [
            'id' => $utilisateur->id,
            'nom' => $utilisateur->name,
            'email' => $utilisateur->email,
            'identifiant' => $utilisateur->role === 'citoyen' ? $utilisateur->identifiant : null,
            'role' => $utilisateur->role,
            'organisme' => $utilisateur->organization === null ? null : [
                'id' => $utilisateur->organization->id,
                'nom' => $utilisateur->organization->nom,
                'sigle' => $utilisateur->organization->sigle,
                'registres' => $utilisateur->organization->registres,
            ],
        ];
    }
}
