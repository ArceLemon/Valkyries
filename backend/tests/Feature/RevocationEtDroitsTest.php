<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UtiliseKuma;
use Tests\TestCase;

class RevocationEtDroitsTest extends TestCase
{
    use RefreshDatabase, UtiliseKuma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerKuma();
    }

    private function avec(string $jeton): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($jeton);
    }

    public function test_un_agent_revoque_perd_l_acces_immediatement(): void
    {
        $jetonAgent = $this->jeton('agent.commerce');
        $jetonResponsable = $this->jeton('responsable.commerce');

        $this->avec($jetonAgent)->getJson('/api/demandes')->assertOk();

        $agent = $this->utilisateur('agent.commerce');
        $this->avec($jetonResponsable)->postJson("/api/equipe/{$agent->id}/revoquer")->assertOk();

        // Le même jeton, qui existait encore une seconde plus tôt, est refusé
        $this->avec($jetonAgent)->getJson('/api/demandes')->assertUnauthorized();
        // Et une nouvelle connexion est refusée
        $this->postJson('/api/auth/connexion', [
            'email' => 'agent.commerce@demo.kumabana.test',
            'mot_de_passe' => 'kumabana-demo',
        ])->assertForbidden();

        $this->assertDatabaseHas('audit_logs', ['action' => 'compte.revoque']);
    }

    public function test_un_compte_desactive_est_bloque_meme_avec_un_jeton_encore_present(): void
    {
        $jeton = $this->jeton('agent.commerce');

        // Désactivation directe, sans passer par la suppression des jetons
        $this->utilisateur('agent.commerce')->forceFill(['actif' => false])->save();

        $this->avec($jeton)->getJson('/api/demandes')->assertForbidden();
    }

    public function test_la_suspension_d_un_organisme_coupe_tous_ses_agents(): void
    {
        $jetonAgent = $this->jeton('agent.commerce');
        $jetonAdmin = $this->jeton('admin');
        $organisme = Organization::where('sigle', 'RCCM-DEMO')->firstOrFail();

        $this->avec($jetonAdmin)->postJson("/api/admin/organisations/{$organisme->id}/suspendre")->assertOk();

        $this->avec($jetonAgent)->getJson('/api/demandes')->assertUnauthorized();
        $this->postJson('/api/auth/connexion', [
            'email' => 'responsable.commerce@demo.kumabana.test',
            'mot_de_passe' => 'kumabana-demo',
        ])->assertForbidden();
    }

    public function test_les_roles_sont_cloisonnes(): void
    {
        // Un citoyen ne pose pas de question
        $this->connecte('ousmane')->postJson('/api/demandes', [])->assertForbidden();
        // Un agent n'administre pas
        $this->connecte('agent.commerce')->getJson('/api/admin/organisations')->assertForbidden();
        // Séparation des pouvoirs : l'administrateur ne peut pas poser de question
        $this->connecte('admin')->postJson('/api/demandes', [])->assertForbidden();
        // L'auditeur lit le journal mais ne modifie rien
        $this->connecte('auditeur')->getJson('/api/journal')->assertOk();
        $this->connecte('auditeur')->postJson('/api/admin/droits', [])->assertForbidden();
        // Sans jeton : rien
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/demandes')->assertUnauthorized();
    }

    public function test_la_source_garde_la_main_sur_les_droits(): void
    {
        // Avant validation : refusé
        $this->poser('agent.mairie', 'certificat_nationalite_valide', ['identifiant' => 'BF-0001'], 'Délivrance d\'un document')->assertForbidden();

        // Le gestionnaire de l'état civil n'est pas concerné par un droit sur la nationalité
        $en_attente = $this->connecte('gestionnaire.nationalite')->getJson('/api/registre/acces')->assertOk();
        $en_attente->assertJsonCount(1, 'donnees');
        $idDroit = $en_attente->json('donnees.0.id');

        $corps = ['reference_juridique' => 'Convention de test n°1'];
        $this->connecte('gestionnaire.etat-civil')->postJson("/api/registre/acces/{$idDroit}/valider", $corps)->assertNotFound();

        // Le gestionnaire du bon registre valide
        $this->connecte('gestionnaire.nationalite')->postJson("/api/registre/acces/{$idDroit}/valider", $corps)->assertOk();

        $this->poser('agent.mairie', 'certificat_nationalite_valide', ['identifiant' => 'BF-0001'], 'Délivrance d\'un document')
            ->assertCreated()
            ->assertJsonPath('reponse', 'oui');
    }

    public function test_un_droit_accorde_par_l_administrateur_attend_la_validation_de_la_source(): void
    {
        $this->connecte('admin')->postJson('/api/admin/droits', [
            'organisme' => 'MAIRIE-DEMO',
            'question' => 'nationalite_burkinabe',
            'finalite' => 'Délivrance d\'un document',
        ])->assertCreated()->assertJsonPath('valide_par_source', false);

        $this->poser('agent.mairie', 'nationalite_burkinabe', ['identifiant' => 'BF-0001'], 'Délivrance d\'un document')->assertForbidden();
    }

    public function test_les_tentatives_de_connexion_sont_limitees(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/connexion', [
                'email' => 'agent.commerce@demo.kumabana.test',
                'mot_de_passe' => 'mauvais',
            ])->assertUnauthorized();
        }

        // Même avec le bon mot de passe, le compte est temporairement bloqué
        $this->postJson('/api/auth/connexion', [
            'email' => 'agent.commerce@demo.kumabana.test',
            'mot_de_passe' => 'kumabana-demo',
        ])->assertStatus(429);

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.echec']);
    }

    public function test_toute_modification_du_catalogue_est_journalisee(): void
    {
        $this->connecte('admin');
        $id = \App\Models\Assertion::where('code', 'majeur')->value('id');

        $this->patchJson("/api/admin/catalogue/{$id}", ['validite_minutes' => 30])->assertOk();

        $ligne = AuditLog::where('action', 'config.modifiee')->firstOrFail();
        $this->assertSame(['validite_minutes' => 1440], $ligne->details['ancien']);
        $this->assertSame(['validite_minutes' => 30], $ligne->details['nouveau']);
    }

    public function test_une_regle_invalide_est_refusee(): void
    {
        $id = \App\Models\Assertion::where('code', 'majeur')->value('id');

        $this->connecte('admin')->patchJson("/api/admin/catalogue/{$id}", ['regle' => 'system("ls")'])->assertStatus(422);
        $this->connecte('admin')->patchJson("/api/admin/catalogue/{$id}", ['regle' => 'age_gte(date_naissance, 21)'])->assertOk();
    }

    public function test_le_responsable_cree_un_agent_et_la_creation_est_journalisee(): void
    {
        $this->connecte('responsable.commerce')->postJson('/api/equipe', [
            'nom' => 'Nouvel Agent (démo)',
            'email' => 'nouveau@demo.kumabana.test',
            'mot_de_passe' => 'un-mot-de-passe-solide',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'nouveau@demo.kumabana.test', 'role' => 'agent']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'compte.cree']);
    }
}
