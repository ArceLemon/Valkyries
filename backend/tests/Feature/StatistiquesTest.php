<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UtiliseKuma;
use Tests\TestCase;

/**
 * Les indicateurs de pilotage comptent les demandes sans jamais exposer de donnée de citoyen.
 */
class StatistiquesTest extends TestCase
{
    use RefreshDatabase, UtiliseKuma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerKuma();
    }

    public function test_les_indicateurs_de_l_organisme_comptent_les_demandes(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0002'])->assertCreated();

        $this->connecte('agent.commerce')
            ->getJson('/api/statistiques/organisme')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('aujourdhui', 2)
            ->assertJsonPath('papiers_evites', 2)
            ->assertJsonPath('taux_reponse', 100)
            ->assertJsonPath('repartition.oui', 1)
            ->assertJsonPath('repartition.non', 1)
            ->assertJsonCount(14, 'serie')
            ->assertJsonPath('top_questions.0.libelle', 'Est majeur');
    }

    public function test_un_organisme_ne_voit_pas_les_demandes_d_un_autre(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();

        $this->connecte('agent.mairie')
            ->getJson('/api/statistiques/organisme')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('taux_reponse', null);
    }

    public function test_la_vue_plateforme_est_reservee_a_l_administration_et_sans_donnee_de_citoyen(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();

        $this->connecte('agent.commerce')->getJson('/api/statistiques/plateforme')->assertForbidden();
        $this->connecte('ousmane')->getJson('/api/statistiques/organisme')->assertForbidden();

        $reponse = $this->connecte('admin')->getJson('/api/statistiques/plateforme')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonStructure(['par_registre', 'organismes_actifs', 'consentements', 'attestations', 'journal']);

        foreach (['BF-0001', '1996-03-14', 'ILBOUDO'] as $secret) {
            $this->assertStringNotContainsString($secret, $reponse->getContent());
        }
    }
}
