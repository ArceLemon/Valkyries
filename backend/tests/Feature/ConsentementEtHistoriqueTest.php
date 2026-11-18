<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UtiliseKuma;
use Tests\TestCase;

/**
 * Épreuve « montrer à l'usager qui a consulté ses données », et règles de consentement.
 */
class ConsentementEtHistoriqueTest extends TestCase
{
    use RefreshDatabase, UtiliseKuma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerKuma();
    }

    public function test_le_casier_attend_le_consentement_du_citoyen(): void
    {
        $reponse = $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0003']);

        $reponse->assertCreated()
            ->assertJsonPath('statut', 'en_attente_consentement')
            ->assertJsonPath('reponse', null);

        // Le registre n'a pas été interrogé tant que le citoyen n'a pas consenti
        $this->assertDatabaseMissing('audit_logs', ['action' => 'donnee.consultee']);
    }

    public function test_un_citoyen_sans_compte_ne_peut_pas_consentir_et_l_agent_en_voit_la_raison(): void
    {
        // BF-0004 n'a pas de compte actif : le consentement ne peut pas être contourné
        $reponse = $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0004']);

        $reponse->assertCreated()
            ->assertJsonPath('statut', 'en_attente_consentement')
            ->assertJsonPath('motif', 'citoyen_sans_compte')
            ->assertJsonPath('reponse', null);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'donnee.consultee']);

        // Le registre n'est jamais interrogé, même après la reprise automatique
        $this->artisan('kuma:reprendre-demandes');
        $this->assertDatabaseMissing('audit_logs', ['action' => 'donnee.consultee']);
    }

    public function test_une_autre_nationalite_est_plus_sensible_et_exige_le_consentement(): void
    {
        $this->connecte('admin')->getJson('/api/admin/catalogue')->assertOk();
        $this->assertSame('consentement', \App\Models\Assertion::where('code', 'possede_nationalite')->value('regime'));
        $this->assertSame('base_legale', \App\Models\Assertion::where('code', 'nationalite_burkinabe')->value('regime'));

        // Même avec un droit validé, la demande attend l'accord du citoyen
        \App\Models\AccessRule::create([
            'organization_id' => \App\Models\Organization::where('sigle', 'RCCM-DEMO')->value('id'),
            'assertion_id' => \App\Models\Assertion::where('code', 'possede_nationalite')->value('id'),
            'finalite' => 'Immatriculation d\'une entreprise',
            'valide_par_source' => true,
        ]);

        $this->poser('agent.commerce', 'possede_nationalite', ['identifiant' => 'BF-0001'], 'Immatriculation d\'une entreprise', 'CI')
            ->assertCreated()
            ->assertJsonPath('statut', 'en_attente_consentement');
    }

    public function test_seul_le_citoyen_concerne_voit_et_decide(): void
    {
        $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0003'])->assertCreated();

        // Une autre citoyenne ne voit rien et ne peut pas décider à sa place
        $this->connecte('fatimata')->getJson('/api/citoyen/demandes-a-valider')->assertJsonCount(0, 'donnees');
        $this->connecte('fatimata')->postJson('/api/citoyen/consentements/1/accepter')->assertNotFound();

        $a_valider = $this->connecte('aminata')->getJson('/api/citoyen/demandes-a-valider')->assertOk();
        $a_valider->assertJsonCount(1, 'donnees');
        $this->assertSame('Registre du commerce (démo)', $a_valider->json('donnees.0.organisme'));
    }

    public function test_acceptation_casier_non_vierge_donne_non_sans_detail_ni_attestation(): void
    {
        $reference = $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0003'])->json('reference');
        $id = $this->connecte('aminata')->getJson('/api/citoyen/demandes-a-valider')->json('donnees.0.id');

        $this->connecte('aminata')->postJson("/api/citoyen/consentements/{$id}/accepter")->assertOk();

        $vue = $this->connecte('agent.commerce')->getJson('/api/demandes/'.$reference)->assertOk();
        $vue->assertJsonPath('statut', 'terminee')->assertJsonPath('reponse', 'non')->assertJsonPath('attestation', null);
        // Aucun détail sur la nature ou le nombre de condamnations
        $this->assertStringNotContainsString('demonstration', $vue->getContent());
        $this->assertStringNotContainsString('2015', $vue->getContent());
    }

    public function test_acceptation_casier_vierge_donne_oui_et_attestation(): void
    {
        $reference = $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0005'])->json('reference');
        $id = $this->connecte('fatimata')->getJson('/api/citoyen/demandes-a-valider')->json('donnees.0.id');
        $this->connecte('fatimata')->postJson("/api/citoyen/consentements/{$id}/accepter")->assertOk();

        $vue = $this->connecte('agent.commerce')->getJson('/api/demandes/'.$reference)->assertOk();
        $vue->assertJsonPath('reponse', 'oui');
        $this->assertNotNull($vue->json('attestation'));
    }

    public function test_refus_du_citoyen_bloque_la_demande_et_efface_les_elements(): void
    {
        $reference = $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0005'])->json('reference');
        $id = $this->connecte('fatimata')->getJson('/api/citoyen/demandes-a-valider')->json('donnees.0.id');
        $this->connecte('fatimata')->postJson("/api/citoyen/consentements/{$id}/refuser")->assertOk();

        $this->connecte('agent.commerce')->getJson('/api/demandes/'.$reference)
            ->assertJsonPath('statut', 'refusee_citoyen')
            ->assertJsonPath('reponse', null);

        $demande = \App\Models\VerificationRequest::firstOrFail();
        $this->assertNull($demande->getRawOriginal('identifiant_enc'));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'donnee.consultee']);
    }

    public function test_retrait_du_consentement_revoque_l_attestation(): void
    {
        $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0005']);
        $id = $this->connecte('fatimata')->getJson('/api/citoyen/demandes-a-valider')->json('donnees.0.id');
        $this->connecte('fatimata')->postJson("/api/citoyen/consentements/{$id}/accepter")->assertOk();

        $jeton = $this->connecte('fatimata')->getJson('/api/citoyen/preuves')->json('donnees.0.jeton');
        $this->postJson('/api/preuves/verifier', ['jeton' => $jeton])->assertJsonPath('etat', 'valide');

        $this->connecte('fatimata')->postJson("/api/citoyen/consentements/{$id}/retirer")->assertOk();

        $this->postJson('/api/preuves/verifier', ['jeton' => $jeton])->assertJsonPath('etat', 'revoquee');
    }

    public function test_consentement_non_donne_a_temps_expire(): void
    {
        $reference = $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0005'])->json('reference');

        $this->travel(3)->days();
        $this->artisan('kuma:reprendre-demandes')->assertExitCode(0);

        $this->connecte('agent.commerce')->getJson('/api/demandes/'.$reference)
            ->assertJsonPath('statut', 'expiree')
            ->assertJsonPath('motif', 'consentement_non_donne');
    }

    public function test_qui_a_consulte_mes_donnees(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();
        $this->poser('agent.mairie', 'vivant', ['identifiant' => 'BF-0001'], 'Délivrance d\'un document')->assertCreated();
        // La consultation d'une autre personne n'apparaît pas dans cet historique
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0005'])->assertCreated();

        $historique = $this->connecte('ousmane')->getJson('/api/citoyen/historique')->assertOk();

        $historique->assertJsonCount(2, 'donnees');
        $organismes = collect($historique->json('donnees'))->pluck('organisme')->all();
        $this->assertContains('Registre du commerce (démo)', $organismes);
        $this->assertContains('Mairie (démo)', $organismes);

        $premiere = collect($historique->json('donnees'))->firstWhere('organisme', 'Registre du commerce (démo)');
        $this->assertSame('Est majeur', $premiere['question']);
        $this->assertSame('Immatriculation d\'une entreprise', $premiere['finalite']);
        $this->assertSame('oui', $premiere['reponse']);

        // L'historique ne montre ni identifiant ni donnée du registre
        $this->assertStringNotContainsString('BF-0001', $historique->getContent());
        $this->assertStringNotContainsString('1996', $historique->getContent());
    }

    public function test_le_citoyen_homonyme_ne_voit_pas_les_consultations_de_l_autre(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();

        $this->connecte('ousmane2')->getJson('/api/citoyen/historique')->assertJsonCount(0, 'donnees');
    }
}
