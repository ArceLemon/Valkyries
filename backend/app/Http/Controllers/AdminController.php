<?php

namespace App\Http\Controllers;

use App\Kuma\Assertions\EvaluateurRegle;
use App\Kuma\Comptes\Revocation;
use App\Kuma\Journal\Journal;
use App\Kuma\Role;
use App\Models\AccessRule;
use App\Models\Assertion;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Espace de l'administrateur de la plateforme. Il ne voit aucune donnée de citoyen :
 * ni les réponses, ni les identifiants.
 */
class AdminController extends Controller
{
    public function organisations(): JsonResponse
    {
        $organismes = Organization::withCount('users')->orderBy('nom')->get()->map(fn (Organization $o) => [
            'id' => $o->id,
            'nom' => $o->nom,
            'sigle' => $o->sigle,
            'tutelle' => $o->tutelle,
            'roles' => $o->roles,
            'registres' => $o->registres,
            'statut' => $o->statut,
            'utilisateurs' => $o->users_count,
        ]);

        return response()->json(['donnees' => $organismes]);
    }

    public function creerOrganisation(Request $request, Journal $journal): JsonResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:160'],
            'sigle' => ['required', 'string', 'max:40', Rule::unique('organizations', 'sigle')],
            'tutelle' => ['nullable', 'string', 'max:160'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['in:demandeuse,fournisseuse'],
            'registres' => ['nullable', 'array'],
            'registres.*' => ['string'],
        ]);

        $organisme = Organization::create($donnees + ['statut' => 'preparation']);

        $journal->ecrire('organisme.cree', [
            'acteur_type' => $request->user()->role,
            'acteur_id' => $request->user()->id,
            'organization_id' => $organisme->id,
            'objet_type' => 'organisme',
            'objet_ref' => $organisme->sigle,
            'resultat' => 'preparation',
        ]);

        return response()->json(['id' => $organisme->id, 'statut' => $organisme->statut], 201);
    }

    public function activer(Request $request, Revocation $revocation, int $id): JsonResponse
    {
        $revocation->activerOrganisme(Organization::findOrFail($id), $request->user());

        return response()->json(['message' => 'Organisme activé.']);
    }

    /**
     * Suspension immédiate : les jetons de tous ses agents sont supprimés.
     */
    public function suspendre(Request $request, Revocation $revocation, int $id): JsonResponse
    {
        $revocation->suspendreOrganisme(Organization::findOrFail($id), $request->user());

        return response()->json(['message' => 'Organisme suspendu.']);
    }

    public function creerDroit(Request $request, Journal $journal): JsonResponse
    {
        $donnees = $request->validate([
            'organisme' => ['required', 'string', 'exists:organizations,sigle'],
            'question' => ['required', 'string', 'exists:assertions,code'],
            'finalite' => ['required', 'string', 'max:200'],
        ]);

        $organisme = Organization::where('sigle', $donnees['organisme'])->firstOrFail();
        $assertion = Assertion::where('code', $donnees['question'])->firstOrFail();

        // Le droit n'est effectif qu'une fois validé par la source.
        $droit = AccessRule::firstOrCreate([
            'organization_id' => $organisme->id,
            'assertion_id' => $assertion->id,
            'finalite' => $donnees['finalite'],
        ], ['valide_par_source' => false]);

        $journal->ecrire('droit.accorde', [
            'acteur_type' => $request->user()->role,
            'acteur_id' => $request->user()->id,
            'organization_id' => $organisme->id,
            'objet_type' => 'droit',
            'objet_ref' => (string) $droit->id,
            'details' => [
                'question' => $assertion->code,
                'finalite' => $donnees['finalite'],
                'validation_source_requise' => ! $droit->valide_par_source,
            ],
            'resultat' => 'en_attente_validation',
        ]);

        return response()->json(['id' => $droit->id, 'valide_par_source' => $droit->valide_par_source], 201);
    }

    public function catalogue(): JsonResponse
    {
        return response()->json(['donnees' => Assertion::orderBy('code')->get()]);
    }

    /**
     * Toute modification du catalogue est journalisée : ancienne et nouvelle valeur.
     */
    public function modifierAssertion(Request $request, Journal $journal, int $id): JsonResponse
    {
        $assertion = Assertion::findOrFail($id);

        $donnees = $request->validate([
            'libelle' => ['sometimes', 'string', 'max:200'],
            'regle' => ['sometimes', 'string', 'max:200'],
            'sensibilite' => ['sometimes', 'in:normale,elevee'],
            'regime' => ['sometimes', 'in:base_legale,consentement'],
            'validite_minutes' => ['sometimes', 'integer', 'min:1', 'max:525600'],
            'publie' => ['sometimes', 'boolean'],
        ]);

        if (isset($donnees['regle']) && ! EvaluateurRegle::valider($donnees['regle'])) {
            abort(422, 'Règle invalide : fonction inconnue ou arguments incorrects.');
        }

        $avant = $assertion->only(array_keys($donnees));
        $assertion->update($donnees);

        $journal->ecrire('config.modifiee', [
            'acteur_type' => $request->user()->role,
            'acteur_id' => $request->user()->id,
            'objet_type' => 'assertion',
            'objet_ref' => $assertion->code,
            'details' => ['ancien' => $avant, 'nouveau' => $donnees],
            'resultat' => 'modifie',
        ]);

        return response()->json($assertion->refresh());
    }

    public function utilisateurs(): JsonResponse
    {
        $utilisateurs = User::with('organization')->where('technique', false)->orderBy('name')->get()
            ->map(fn (User $u) => $this->vueUtilisateur($u));

        return response()->json(['donnees' => $utilisateurs]);
    }

    public function revoquer(Request $request, Revocation $revocation, int $id): JsonResponse
    {
        $cible = User::findOrFail($id);

        if ($cible->id === $request->user()->id) {
            abort(422, 'Vous ne pouvez pas révoquer votre propre compte.');
        }

        $revocation->revoquerUtilisateur($cible, $request->user());

        return response()->json(['message' => 'Compte révoqué.']);
    }

    /**
     * Fiche d'un organisme : ses comptes, ses droits et ce qu'il reste à faire
     * pour qu'il soit opérationnel.
     */
    public function organisation(int $id): JsonResponse
    {
        $organisme = Organization::with(['users' => fn ($q) => $q->where('technique', false)->orderBy('name'), 'droits.assertion'])
            ->findOrFail($id);

        return response()->json([
            'id' => $organisme->id,
            'nom' => $organisme->nom,
            'sigle' => $organisme->sigle,
            'tutelle' => $organisme->tutelle,
            'roles' => $organisme->roles,
            'registres' => $organisme->registres,
            'statut' => $organisme->statut,
            'utilisateurs' => $organisme->users->map(fn (User $u) => $this->vueUtilisateur($u))->values(),
            'droits' => $organisme->droits->map(fn (AccessRule $d) => [
                'id' => $d->id,
                'question' => $d->assertion->code,
                'libelle' => $d->assertion->libelle,
                'registre' => $d->assertion->registre,
                'finalite' => $d->finalite,
                'etat' => $d->etat(),
                'expire_le' => $d->expire_le?->toIso8601String(),
            ])->values(),
            'roles_comptes' => self::rolesPourOrganisme($organisme),
        ]);
    }

    /**
     * L'administrateur crée un compte et lui attribue un rôle. Le rôle doit
     * correspondre à l'organisme : un agent dans un organisme demandeur, un
     * gestionnaire dans un organisme qui détient un registre, etc.
     */
    public function creerUtilisateur(Request $request, Journal $journal): JsonResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'mot_de_passe' => ['required', 'string', 'min:12'],
            'role' => ['required', Rule::in(self::ROLES_ATTRIBUABLES)],
            'organisme_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ]);

        $organisme = isset($donnees['organisme_id']) ? Organization::find($donnees['organisme_id']) : null;
        $this->verifierRattachement($donnees['role'], $organisme);

        $compte = User::create([
            'name' => $donnees['nom'],
            'email' => $donnees['email'],
            'password' => $donnees['mot_de_passe'],
            'role' => $donnees['role'],
            'organization_id' => $organisme?->id,
            'actif' => true,
        ]);

        $journal->ecrire('compte.cree', [
            'acteur_type' => $request->user()->role,
            'acteur_id' => $request->user()->id,
            'organization_id' => $compte->organization_id,
            'objet_type' => 'utilisateur',
            'objet_ref' => (string) $compte->id,
            'details' => ['role_cible' => $compte->role],
            'resultat' => 'cree',
        ]);

        return response()->json($this->vueUtilisateur($compte->load('organization')), 201);
    }

    /**
     * Changement de rôle : les sessions ouvertes sont fermées, car le jeton porte
     * l'ancien rôle. Le compte d'un citoyen ne change jamais de rôle.
     */
    public function modifierRole(Request $request, Journal $journal, int $id): JsonResponse
    {
        $cible = User::with('organization')->findOrFail($id);
        $donnees = $request->validate([
            'role' => ['required', Rule::in(self::ROLES_ATTRIBUABLES)],
        ]);

        if ($cible->id === $request->user()->id) {
            abort(422, 'Vous ne pouvez pas changer votre propre rôle.');
        }
        if ($cible->role === Role::Citoyen->value || $cible->technique) {
            abort(422, 'Le rôle de ce compte ne peut pas être changé.');
        }
        $this->verifierRattachement($donnees['role'], $cible->organization);

        $ancien = $cible->role;
        $cible->forceFill(['role' => $donnees['role']])->save();
        $cible->tokens()->delete();

        $journal->ecrire('compte.role_modifie', [
            'acteur_type' => $request->user()->role,
            'acteur_id' => $request->user()->id,
            'organization_id' => $cible->organization_id,
            'objet_type' => 'utilisateur',
            'objet_ref' => (string) $cible->id,
            'details' => ['ancien' => $ancien, 'nouveau' => $cible->role],
            'resultat' => 'modifie',
        ]);

        return response()->json($this->vueUtilisateur($cible));
    }

    public function reactiver(Request $request, Journal $journal, int $id): JsonResponse
    {
        $cible = User::findOrFail($id);
        $cible->forceFill(['actif' => true])->save();

        $journal->ecrire('compte.reactive', [
            'acteur_type' => $request->user()->role,
            'acteur_id' => $request->user()->id,
            'organization_id' => $cible->organization_id,
            'objet_type' => 'utilisateur',
            'objet_ref' => (string) $cible->id,
            'details' => ['role_cible' => $cible->role],
            'resultat' => 'actif',
        ]);

        return response()->json(['message' => 'Compte réactivé.']);
    }

    /** Rôles que l'administrateur peut attribuer. Un citoyen crée lui-même son compte. */
    private const ROLES_ATTRIBUABLES = ['agent', 'responsable', 'gestionnaire', 'administrateur', 'auditeur'];

    /**
     * Rôles possibles pour les comptes d'un organisme, selon ce qu'il fait.
     *
     * @return list<string>
     */
    public static function rolesPourOrganisme(?Organization $organisme): array
    {
        if ($organisme === null) {
            return ['administrateur', 'auditeur'];
        }

        $roles = [];
        if ($organisme->aRole('demandeuse')) {
            array_push($roles, 'responsable', 'agent');
        }
        if ($organisme->aRole('fournisseuse')) {
            $roles[] = 'gestionnaire';
        }

        return $roles;
    }

    private function verifierRattachement(string $role, ?Organization $organisme): void
    {
        if (in_array($role, self::rolesPourOrganisme($organisme), true)) {
            return;
        }

        $message = match (true) {
            $organisme === null => 'Ce rôle doit être rattaché à un organisme.',
            in_array($role, ['administrateur', 'auditeur'], true) => 'Un administrateur ou un auditeur n\'appartient à aucun organisme.',
            $role === 'gestionnaire' => 'Un gestionnaire doit appartenir à un organisme qui détient un registre (fournisseur).',
            default => 'Un agent ou un responsable doit appartenir à un organisme demandeur.',
        };

        throw ValidationException::withMessages(['role' => $message]);
    }

    /**
     * @return array<string, mixed>
     */
    private function vueUtilisateur(User $u): array
    {
        return [
            'id' => $u->id,
            'nom' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'organisme' => $u->organization?->sigle,
            'organisme_id' => $u->organization_id,
            'actif' => $u->actif,
        ];
    }
}
