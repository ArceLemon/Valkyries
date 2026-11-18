<?php

namespace App\Kuma\Comptes;

use App\Kuma\Journal\Journal;
use App\Kuma\Registres\GestionnaireRegistres;
use App\Kuma\Registres\Recherche;
use App\Kuma\Registres\ResultatRegistre;
use App\Kuma\Role;
use App\Kuma\Support\Normalisation;
use App\Kuma\Support\Pseudonymiseur;
use App\Models\User;

/**
 * Un citoyen crée lui-même son compte avec son identifiant unique, son nom,
 * ses prénoms, son numéro de téléphone et un mot de passe.
 *
 * Contrôles, auprès du registre d'état civil :
 *  - l'identifiant doit exister et la personne être en vie ;
 *  - le nom et les prénoms doivent être ceux de l'état civil (comparés sans
 *    accents ni casse, mais sans approximation) ;
 *  - si le registre connaît un numéro pour cette personne, le numéro saisi doit
 *    être celui-là : on ne peut pas ouvrir un compte sur l'identifiant d'un autre
 *    sans connaître son numéro.
 * La fiche du registre ne sert qu'à cette comparaison : elle n'est ni conservée
 * ni journalisée. Le message d'échec est le même dans tous les cas, pour ne rien
 * révéler sur le registre.
 *
 * Le numéro servira aussi au canal USSD (citoyen sans smartphone), prévu ensuite.
 */
final class Inscription
{
    public const REGISTRE = 'etat_civil';

    public function __construct(
        private readonly GestionnaireRegistres $registres,
        private readonly Journal $journal,
        private readonly Pseudonymiseur $pseudonymes,
    ) {}

    /**
     * @param  array{identifiant: string, nom: string, prenoms: string, telephone: string, mot_de_passe: string}  $donnees
     */
    public function inscrire(array $donnees): User
    {
        $identifiant = mb_strtoupper(trim($donnees['identifiant']));
        $sujet = $this->pseudonymes->pour($identifiant);

        if (User::where('identifiant', $identifiant)->exists()) {
            $this->tracer($sujet, 'deja_inscrit');
            abort(409, 'Un compte existe déjà pour cet identifiant. Connectez-vous, ou contactez l\'assistance si ce n\'est pas vous.');
        }

        $resultat = $this->registres->interroger(self::REGISTRE, new Recherche($identifiant));

        if ($resultat->estIndisponible()) {
            $this->tracer($sujet, 'registre_indisponible');
            abort(503, 'Le registre d\'état civil ne répond pas pour le moment. Réessayez dans quelques minutes.');
        }

        // La donnée du registre reste dans cette méthode : seule la concordance compte.
        $connu = $resultat->donnees['telephone'] ?? null;
        $concorde = $resultat->statut === ResultatRegistre::TROUVE
            && $this->memeIdentite($resultat->donnees, $donnees)
            && ($resultat->donnees['statut'] ?? 'vivant') !== 'decede'
            && ($connu === null || $connu === ''
                || Normalisation::telephone((string) $connu) === Normalisation::telephone($donnees['telephone']));

        if (! $concorde) {
            $this->tracer($sujet, 'identite_non_verifiee');
            abort(422, 'Ces informations ne correspondent pas à l\'état civil. Vérifiez-les, ou rapprochez-vous de votre mairie.');
        }

        $citoyen = User::create([
            'name' => trim($donnees['prenoms']).' '.mb_convert_case(trim($donnees['nom']), MB_CASE_TITLE),
            'email' => null,
            'password' => $donnees['mot_de_passe'],
            'role' => Role::Citoyen->value,
            'identifiant' => $identifiant,
            'telephone' => trim($donnees['telephone']),
            'actif' => true,
        ]);

        $this->journal->ecrire('compte.inscription', [
            'acteur_type' => Role::Citoyen->value,
            'acteur_id' => $citoyen->id,
            'objet_type' => 'utilisateur',
            'objet_ref' => (string) $citoyen->id,
            'sujet_pseudo' => $sujet,
            'details' => [
                'verification' => self::REGISTRE,
                'telephone_controle' => $connu !== null && $connu !== '',
            ],
            'resultat' => 'cree',
        ]);

        return $citoyen;
    }

    /**
     * @param  array<string, mixed>  $fiche  données du registre
     * @param  array<string, string>  $saisie
     */
    private function memeIdentite(array $fiche, array $saisie): bool
    {
        $prenoms = $fiche['prenoms'] ?? '';
        if (is_array($prenoms)) {
            $prenoms = implode(' ', $prenoms);
        }

        return Normalisation::texte((string) ($fiche['nom'] ?? '')) === Normalisation::texte($saisie['nom'])
            && Normalisation::texte((string) $prenoms) === Normalisation::texte($saisie['prenoms']);
    }

    private function tracer(string $sujet, string $cause): void
    {
        $this->journal->ecrire('compte.inscription_refusee', [
            'acteur_type' => 'inconnu',
            'objet_type' => 'utilisateur',
            'sujet_pseudo' => $sujet,
            'details' => ['cause' => $cause],
            'resultat' => 'refuse',
        ]);
    }
}
