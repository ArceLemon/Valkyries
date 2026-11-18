<?php

namespace App\Kuma\Api;

use App\Kuma\Journal\Journal;
use App\Kuma\Role;
use App\Models\ApiCredential;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Clés d'API d'un organisme : permettent au logiciel d'une administration d'appeler
 * KUMA BANA sans passer par le portail.
 *
 * Format : kb_<identifiant 12 hex>_<secret 40 hex>. Le secret n'est montré qu'une fois
 * à la création ; on ne conserve que son empreinte SHA-256 (le secret est aléatoire,
 * à forte entropie, donc une empreinte rapide suffit).
 */
final class GestionCles
{
    public function __construct(private readonly Journal $journal) {}

    /**
     * @param  array{nom: string, questions?: list<string>|null, quota_par_minute?: int|null, expire_le?: string|null, cle_publique?: string|null}  $donnees
     * @return array{credential: ApiCredential, cle: string}
     */
    public function creer(Organization $organisme, User $auteur, array $donnees): array
    {
        $kid = bin2hex(random_bytes(6));
        $secret = bin2hex(random_bytes(20));

        $credential = DB::transaction(function () use ($organisme, $auteur, $donnees, $kid, $secret) {
            // Compte technique : porte les demandes de cette clé, ne peut pas se connecter.
            $technique = User::create([
                'name' => 'Compte API : '.$donnees['nom'],
                'email' => "api-{$kid}@api.kumabana.invalid",
                'password' => bin2hex(random_bytes(24)),
                'role' => Role::Agent->value,
                'organization_id' => $organisme->id,
                'actif' => true,
                'technique' => true,
            ]);

            return ApiCredential::create([
                'organization_id' => $organisme->id,
                'user_id' => $technique->id,
                'nom' => $donnees['nom'],
                'kid' => $kid,
                'secret_hash' => hash('sha256', $secret),
                'cle_publique' => $donnees['cle_publique'] ?? null,
                'questions' => $donnees['questions'] ?? null,
                'quota_par_minute' => $donnees['quota_par_minute'] ?? null,
                'expire_le' => $donnees['expire_le'] ?? null,
                'cree_par' => $auteur->id,
            ]);
        });

        $this->journal->ecrire('cle.creee', [
            'acteur_type' => $auteur->role,
            'acteur_id' => $auteur->id,
            'organization_id' => $organisme->id,
            'objet_type' => 'cle_api',
            'objet_ref' => $kid,
            'details' => [
                'signature_obligatoire' => $credential->signatureObligatoire(),
                'questions_limitees' => $credential->questions !== null,
                'expire' => $credential->expire_le !== null,
            ],
            'resultat' => 'creee',
        ]);

        return ['credential' => $credential, 'cle' => "kb_{$kid}_{$secret}"];
    }

    public function revoquer(ApiCredential $credential, User $auteur): void
    {
        $credential->update(['revoquee_le' => now()]);
        $credential->technique->forceFill(['actif' => false])->save();

        $this->journal->ecrire('cle.revoquee', [
            'acteur_type' => $auteur->role,
            'acteur_id' => $auteur->id,
            'organization_id' => $credential->organization_id,
            'objet_type' => 'cle_api',
            'objet_ref' => $credential->kid,
            'resultat' => 'revoquee',
        ]);
    }
}
