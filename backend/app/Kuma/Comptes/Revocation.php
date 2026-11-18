<?php

namespace App\Kuma\Comptes;

use App\Kuma\Journal\Journal;
use App\Models\Organization;
use App\Models\User;

/**
 * Révocation immédiate : un compte désactivé perd ses jetons à l'instant même,
 * et la vérification du compte à chaque requête bloque tout accès résiduel.
 */
final class Revocation
{
    public function __construct(private readonly Journal $journal) {}

    public function revoquerUtilisateur(User $cible, User $auteur): void
    {
        $cible->forceFill(['actif' => false])->save();
        $cible->tokens()->delete();

        $this->journal->ecrire('compte.revoque', [
            'acteur_type' => $auteur->role,
            'acteur_id' => $auteur->id,
            'organization_id' => $cible->organization_id,
            'objet_type' => 'utilisateur',
            'objet_ref' => (string) $cible->id,
            'details' => ['role_cible' => $cible->role],
            'resultat' => 'revoque',
        ]);
    }

    public function suspendreOrganisme(Organization $organisme, User $auteur): void
    {
        $organisme->update(['statut' => 'suspendu']);

        foreach ($organisme->users as $utilisateur) {
            $utilisateur->tokens()->delete();
        }

        $this->journal->ecrire('organisme.suspendu', [
            'acteur_type' => $auteur->role,
            'acteur_id' => $auteur->id,
            'organization_id' => $organisme->id,
            'objet_type' => 'organisme',
            'objet_ref' => $organisme->sigle,
            'resultat' => 'suspendu',
        ]);
    }

    public function activerOrganisme(Organization $organisme, User $auteur): void
    {
        $organisme->update(['statut' => 'actif']);

        $this->journal->ecrire('organisme.active', [
            'acteur_type' => $auteur->role,
            'acteur_id' => $auteur->id,
            'organization_id' => $organisme->id,
            'objet_type' => 'organisme',
            'objet_ref' => $organisme->sigle,
            'resultat' => 'actif',
        ]);
    }
}
