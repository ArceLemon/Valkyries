<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\UtiliseKuma;
use Tests\TestCase;

/**
 * Épreuve « tenir quand un registre tombe en panne » : jamais de faux succès,
 * la demande attend puis reprend toute seule.
 */
class PanneEtRepriseTest extends TestCase
{
    use RefreshDatabase, UtiliseKuma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerKuma();
    }

    public function test_registre_en_panne_la_demande_attend_sans_reponse_inventee(): void
    {
        $this->pannes['etat-civil'] = 'down';

        $reponse = $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001']);

        $reponse->assertCreated()
            ->assertJsonPath('statut', 'en_attente_registre')
            ->assertJsonPath('reponse', null)
            ->assertJsonPath('attestation', null);

        $this->assertDatabaseHas('audit_logs', ['action' => 'registre.indisponible']);
        // Aucune consultation n'a abouti : rien à montrer au citoyen
        $this->assertDatabaseMissing('audit_logs', ['action' => 'donnee.consultee']);
    }

    public function test_la_demande_reprend_seule_quand_le_registre_revient(): void
    {
        $this->pannes['etat-civil'] = 'down';
        $reference = $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->json('reference');

        // Le registre est rétabli ; la reprise automatique relance la demande
        $this->pannes = [];
        $this->travel(2)->minutes();
        $this->artisan('kuma:reprendre-demandes')->assertExitCode(0);

        $this->connecte('agent.commerce')->getJson('/api/demandes/'.$reference)
            ->assertOk()
            ->assertJsonPath('statut', 'terminee')
            ->assertJsonPath('reponse', 'oui');
    }

    public function test_la_demande_repart_des_que_la_sonde_voit_le_registre_revenir(): void
    {
        $this->pannes['etat-civil'] = 'down';
        $reference = $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->json('reference');

        // Pendant la panne, la reprise n'avance pas : le registre ne répond toujours pas
        $this->artisan('kuma:sonder-registres');
        $this->artisan('kuma:reprendre-demandes');
        $this->connecte('agent.commerce')->getJson('/api/demandes/'.$reference)
            ->assertJsonPath('statut', 'en_attente_registre');

        // Le registre revient : la sonde le constate, et la demande repart tout de suite,
        // sans attendre la fin du délai de reprise (aucun voyage dans le temps ici)
        $this->pannes = [];
        $this->artisan('kuma:sonder-registres');
        $this->artisan('kuma:reprendre-demandes');

        $this->connecte('agent.commerce')->getJson('/api/demandes/'.$reference)
            ->assertJsonPath('statut', 'terminee')
            ->assertJsonPath('reponse', 'oui');
    }

    public function test_une_demande_n_est_pas_relancee_tant_que_le_registre_est_en_panne(): void
    {
        $this->pannes['etat-civil'] = 'down';
        $reference = $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->json('reference');
        \Illuminate\Support\Facades\Http::assertSentCount(1);

        // Plusieurs passes de reprise pendant la panne : aucun nouvel appel ne part
        for ($i = 0; $i < 3; $i++) {
            $this->artisan('kuma:reprendre-demandes');
        }
        // (la sonde, elle, interroge la santé du registre : on ne la lance pas ici)
        \Illuminate\Support\Facades\Http::assertSentCount(1);

        $this->connecte('agent.commerce')->getJson('/api/demandes/'.$reference)
            ->assertJsonPath('statut', 'en_attente_registre');
    }

    public function test_le_coupe_circuit_arrete_d_appeler_un_registre_en_panne(): void
    {
        $this->pannes['etat-civil'] = 'down';

        // Trois échecs ouvrent le circuit...
        for ($i = 0; $i < 3; $i++) {
            $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();
        }
        Http::assertSentCount(3);

        // ...la quatrième demande n'appelle plus le registre
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])
            ->assertJsonPath('statut', 'en_attente_registre');
        Http::assertSentCount(3);
    }

    public function test_le_circuit_se_referme_apres_la_pause(): void
    {
        $this->pannes['etat-civil'] = 'down';
        for ($i = 0; $i < 3; $i++) {
            $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001']);
        }

        $this->pannes = [];
        $this->travel(60)->seconds();

        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])
            ->assertJsonPath('reponse', 'oui');
    }

    public function test_la_panne_d_un_registre_n_affecte_pas_les_autres(): void
    {
        $this->pannes['casier'] = 'down';

        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])
            ->assertJsonPath('reponse', 'oui');
    }

    public function test_l_etat_des_registres_est_visible(): void
    {
        $this->pannes['nationalite'] = 'down';
        $this->artisan('kuma:sonder-registres')->assertExitCode(0);

        $reponse = $this->connecte('agent.commerce')->getJson('/api/registres')->assertOk();

        $etats = collect($reponse->json('donnees'))->pluck('etat', 'registre');
        $this->assertSame('en_service', $etats['etat_civil']);
        $this->assertSame('panne', $etats['nationalite']);
    }

    public function test_une_demande_trop_longtemps_en_attente_expire_sans_reponse(): void
    {
        config(['kuma.reprise.max_tentatives' => 2]);
        $this->pannes['etat-civil'] = 'down';

        $reference = $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->json('reference');
        $this->travel(2)->minutes();
        $this->artisan('kuma:reprendre-demandes');

        $this->connecte('agent.commerce')->getJson('/api/demandes/'.$reference)
            ->assertJsonPath('statut', 'expiree')
            ->assertJsonPath('reponse', null);
    }
}
