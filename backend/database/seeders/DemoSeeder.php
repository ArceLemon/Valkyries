<?php

namespace Database\Seeders;

use App\Kuma\Role;
use App\Models\AccessRule;
use App\Models\Assertion;
use App\Models\Organization;
use App\Models\Procedure;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Jeu de démonstration. Tout est fictif : organismes, agents, citoyens.
 * Les citoyens correspondent à ceux des registres de démonstration (registres-demo/).
 *
 * Mot de passe de TOUS les comptes de démonstration : kumabana-demo
 */
class DemoSeeder extends Seeder
{
    public const MOT_DE_PASSE = 'kumabana-demo';

    /** Aucune base juridique réelle n'est affirmée : à confirmer par l'administration responsable. */
    private const BASE_JURIDIQUE = 'À confirmer par l\'administration responsable du registre (démonstration)';

    private const DESCRIPTIONS = [
        'majeur' => 'La personne a 18 ans révolus. Aucune date de naissance n\'est transmise.',
        'age_minimum' => 'La personne a au moins l\'âge indiqué en paramètre.',
        'vivant' => 'La personne est enregistrée comme vivante à l\'état civil.',
        'nationalite_burkinabe' => 'La personne est de nationalité burkinabè.',
        'possede_nationalite' => 'La personne possède la nationalité indiquée en paramètre. Plus sensible que « est burkinabè » : consentement requis.',
        'certificat_nationalite_valide' => 'Le certificat de nationalité de la personne n\'est pas expiré.',
        'casier_vierge' => 'Le registre ne signale aucune inscription à l\'extrait destiné aux administrations. Réponse sans aucun détail ; consentement requis.',
    ];

    public function run(): void
    {
        $organismes = $this->organismes();
        $this->comptes($organismes);
        $assertions = $this->catalogue();
        $this->droits($organismes, $assertions);
        $this->demarches($assertions);
    }

    /**
     * @return array<string, Organization>
     */
    private function organismes(): array
    {
        $liste = [
            'RCCM-DEMO' => ['Registre du commerce (démo)', ['demandeuse'], null],
            'MAIRIE-DEMO' => ['Mairie (démo)', ['demandeuse'], null],
            'DEC-DEMO' => ['Direction de l\'état civil (démo)', ['fournisseuse'], ['etat_civil']],
            'DNAT-DEMO' => ['Direction de la nationalité (démo)', ['fournisseuse'], ['nationalite']],
            'CJN-DEMO' => ['Casier judiciaire national (démo)', ['fournisseuse'], ['casier']],
        ];

        $resultat = [];
        foreach ($liste as $sigle => [$nom, $roles, $registres]) {
            $resultat[$sigle] = Organization::updateOrCreate(['sigle' => $sigle], [
                'nom' => $nom,
                'roles' => $roles,
                'registres' => $registres,
                'statut' => 'actif',
            ]);
        }

        return $resultat;
    }

    /**
     * @param  array<string, Organization>  $organismes
     */
    private function comptes(array $organismes): void
    {
        $comptes = [
            ['Administrateur de la plateforme', 'admin', Role::Administrateur, null, null],
            ['Auditeur (démo)', 'auditeur', Role::Auditeur, null, null],
            ['Agent du commerce (démo)', 'agent.commerce', Role::Agent, 'RCCM-DEMO', null],
            ['Responsable du commerce (démo)', 'responsable.commerce', Role::Responsable, 'RCCM-DEMO', null],
            ['Agent de mairie (démo)', 'agent.mairie', Role::Agent, 'MAIRIE-DEMO', null],
            ['Gestionnaire état civil (démo)', 'gestionnaire.etat-civil', Role::Gestionnaire, 'DEC-DEMO', null],
            ['Gestionnaire nationalité (démo)', 'gestionnaire.nationalite', Role::Gestionnaire, 'DNAT-DEMO', null],
            ['Gestionnaire casier (démo)', 'gestionnaire.casier', Role::Gestionnaire, 'CJN-DEMO', null],
            ['Ousmane Ilboudo (fictif)', 'ousmane', Role::Citoyen, null, 'BF-0001'],
            ['Ousmane Ilboudo, homonyme (fictif)', 'ousmane2', Role::Citoyen, null, 'BF-0002'],
            ['Aminata Sawadogo (fictive)', 'aminata', Role::Citoyen, null, 'BF-0003'],
            ['Fatimata Ouedraogo (fictive)', 'fatimata', Role::Citoyen, null, 'BF-0005'],
        ];

        foreach ($comptes as [$nom, $alias, $role, $sigle, $identifiant]) {
            User::updateOrCreate(['email' => $alias.'@demo.kumabana.test'], [
                'name' => $nom,
                'password' => self::MOT_DE_PASSE,
                'role' => $role->value,
                'organization_id' => $sigle === null ? null : $organismes[$sigle]->id,
                'identifiant' => $identifiant,
                'actif' => true,
                // Numéro fictif, pour montrer le message d'alerte.
                'telephone' => $identifiant === null ? null : '+226 70 00 '.substr($identifiant, -4, 2).' '.substr($identifiant, -2),
            ]);
        }
    }

    /**
     * Catalogue initial par registre (section 8.1 du dossier). Les règles utilisent
     * la liste fermée de fonctions de EvaluateurRegle.
     *
     * @return array<string, Assertion>
     */
    private function catalogue(): array
    {
        $liste = [
            ['majeur', 'Est majeur', 'etat_civil', 'age_gte(date_naissance, 18)', 'normale', 'base_legale', 1440, false],
            ['age_minimum', 'A au moins l\'âge indiqué', 'etat_civil', 'age_gte(date_naissance, {parametre})', 'normale', 'base_legale', 1440, true],
            ['vivant', 'Est vivant', 'etat_civil', 'equals(statut, "vivant")', 'normale', 'base_legale', 1440, false],
            ['nationalite_burkinabe', 'Est de nationalité burkinabè', 'nationalite', 'equals(nationalite, "BF")', 'normale', 'base_legale', 1440, false],
            // Révéler qu'une personne a une AUTRE nationalité est plus sensible que
            // « est burkinabè » : consentement requis, preuve courte.
            ['possede_nationalite', 'Possède la nationalité indiquée', 'nationalite', 'equals(nationalite, {parametre})', 'elevee', 'consentement', 60, true],
            ['certificat_nationalite_valide', 'Certificat de nationalité valide', 'nationalite', 'date_gte(certificat_expire, "today")', 'normale', 'base_legale', 1440, false],
            // Le casier est plus sensible : consentement requis, preuve courte, réponse sans détail.
            ['casier_vierge', 'Casier judiciaire vierge', 'casier', 'is_empty(condamnations)', 'elevee', 'consentement', 60, false],
        ];

        $resultat = [];
        foreach ($liste as [$code, $libelle, $registre, $regle, $sensibilite, $regime, $validite, $parametree]) {
            $resultat[$code] = Assertion::updateOrCreate(['code' => $code], [
                'libelle' => $libelle,
                'registre' => $registre,
                'regle' => $regle,
                'sensibilite' => $sensibilite,
                'regime' => $regime,
                'validite_minutes' => $validite,
                'parametree' => $parametree,
                'publie' => true,
                'description' => self::DESCRIPTIONS[$code] ?? null,
                'base_juridique' => self::BASE_JURIDIQUE,
            ]);
        }

        return $resultat;
    }

    /**
     * @param  array<string, Organization>  $organismes
     * @param  array<string, Assertion>  $assertions
     */
    private function droits(array $organismes, array $assertions): void
    {
        $droits = [
            // Registre du commerce : tout ce qu'il faut pour une immatriculation
            ['RCCM-DEMO', 'majeur', 'Immatriculation d\'une entreprise', true],
            ['RCCM-DEMO', 'nationalite_burkinabe', 'Immatriculation d\'une entreprise', true],
            ['RCCM-DEMO', 'casier_vierge', 'Immatriculation d\'une entreprise', true],
            // Mairie : seulement deux questions. Elle ne peut pas demander le casier.
            ['MAIRIE-DEMO', 'majeur', 'Délivrance d\'un document', true],
            ['MAIRIE-DEMO', 'vivant', 'Délivrance d\'un document', true],
            // Mairie : inscription sur les listes électorales (état civil et nationalité, sans consentement)
            ['MAIRIE-DEMO', 'majeur', 'Inscription sur les listes électorales', true],
            ['MAIRIE-DEMO', 'nationalite_burkinabe', 'Inscription sur les listes électorales', true],
            ['MAIRIE-DEMO', 'vivant', 'Inscription sur les listes électorales', true],
            // Droit demandé mais pas encore validé par le gestionnaire du registre
            ['MAIRIE-DEMO', 'certificat_nationalite_valide', 'Délivrance d\'un document', false],
        ];

        foreach ($droits as [$sigle, $code, $finalite, $valide]) {
            AccessRule::updateOrCreate([
                'organization_id' => $organismes[$sigle]->id,
                'assertion_id' => $assertions[$code]->id,
                'finalite' => $finalite,
            ], [
                'valide_par_source' => $valide,
                'valide_le' => $valide ? now() : null,
                'justification' => 'Droit de démonstration pour '.$organismes[$sigle]->nom,
                'reference_juridique' => $valide ? 'Convention de démonstration DEMO-2026-01 (fictive)' : null,
                'expire_le' => $valide ? now()->addYear() : null,
            ]);
        }
    }

    /**
     * @param  array<string, Assertion>  $assertions
     */
    private function demarches(array $assertions): void
    {
        $creation = Procedure::updateOrCreate(['code' => 'creation-entreprise'], [
            'libelle' => 'Création d\'entreprise',
            'finalite' => 'Immatriculation d\'une entreprise',
        ]);
        $creation->assertions()->sync([
            $assertions['majeur']->id => ['parametre' => null],
            $assertions['nationalite_burkinabe']->id => ['parametre' => null],
            $assertions['casier_vierge']->id => ['parametre' => null],
        ]);

        $document = Procedure::updateOrCreate(['code' => 'delivrance-document'], [
            'libelle' => 'Délivrance d\'un document',
            'finalite' => 'Délivrance d\'un document',
        ]);
        $document->assertions()->sync([
            $assertions['majeur']->id => ['parametre' => null],
            $assertions['vivant']->id => ['parametre' => null],
        ]);

        $listes = Procedure::updateOrCreate(['code' => 'inscription-listes-electorales'], [
            'libelle' => 'Inscription sur les listes électorales',
            'finalite' => 'Inscription sur les listes électorales',
        ]);
        $listes->assertions()->sync([
            $assertions['majeur']->id => ['parametre' => null],
            $assertions['nationalite_burkinabe']->id => ['parametre' => null],
            $assertions['vivant']->id => ['parametre' => null],
        ]);
    }
}
