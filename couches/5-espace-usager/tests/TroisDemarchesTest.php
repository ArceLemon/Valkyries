<?php

namespace Tests\Couches\EspaceUsager;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UtiliseKuma;
use Tests\TestCase;

/**
 * Trois démarches de bout en bout, sans aucune pièce à fournir par l'usager.
 * Pour chacune : les réponses arrivent, le citoyen retrouve chaque consultation
 * dans son espace, et rien d'autre que « oui » ou « non » n'a circulé.
 */
class TroisDemarchesTest extends TestCase
{
    use RefreshDatabase, UtiliseKuma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerKuma();
    }

    public function test_le_catalogue_propose_trois_demarches(): void
    {
        $codes = collect($this->connecte('agent.commerce')->getJson('/api/demarches')->assertOk()->json('donnees'))->pluck('code')->all();

        $this->assertEqualsCanonicalizing(['creation-entreprise', 'delivrance-document', 'inscription-listes-electorales'], $codes);
    }

    public function test_demarche_1_creation_d_entreprise_avec_le_consentement_pour_le_casier(): void
    {
        $this->connecte('agent.commerce')
            ->postJson('/api/demarches/creation-entreprise/lancer', ['citoyen' => ['identifiant' => 'BF-0005']])
            ->assertCreated();

        // Majorité et nationalité : tout de suite. Casier : il attend le citoyen.
        $demandes = collect($this->connecte('agent.commerce')->getJson('/api/demandes')->json('donnees'))->keyBy('question');
        $this->assertSame('oui', $demandes['majeur']['reponse']);
        $this->assertSame('oui', $demandes['nationalite_burkinabe']['reponse']);
        $this->assertSame('en_attente_consentement', $demandes['casier_vierge']['statut']);

        $id = $this->connecte('fatimata')->getJson('/api/citoyen/demandes-a-valider')->json('donnees.0.id');
        $this->connecte('fatimata')->postJson("/api/citoyen/consentements/{$id}/accepter")->assertOk();

        $this->assertSame('oui', collect($this->connecte('agent.commerce')->getJson('/api/demandes')->json('donnees'))->firstWhere('question', 'casier_vierge')['reponse']);
        $this->assertCount(3, $this->historiqueDe('fatimata', 'donnee.consultee'));
    }

    public function test_demarche_2_delivrance_d_un_document(): void
    {
        $this->connecte('agent.mairie')
            ->postJson('/api/demarches/delivrance-document/lancer', ['citoyen' => ['identifiant' => 'BF-0001']])
            ->assertCreated();

        $reponses = collect($this->connecte('agent.mairie')->getJson('/api/demandes')->json('donnees'))->pluck('reponse', 'question');
        $this->assertSame('oui', $reponses['majeur']);
        $this->assertSame('oui', $reponses['vivant']);
        $this->assertCount(2, $this->historiqueDe('ousmane', 'donnee.consultee'));
    }

    public function test_demarche_3_inscription_sur_les_listes_electorales_sans_aucun_consentement(): void
    {
        $this->connecte('agent.mairie')
            ->postJson('/api/demarches/inscription-listes-electorales/lancer', ['citoyen' => ['identifiant' => 'BF-0001']])
            ->assertCreated();

        $demandes = collect($this->connecte('agent.mairie')->getJson('/api/demandes')->json('donnees'));
        $this->assertCount(3, $demandes);
        $this->assertSame(['oui', 'oui', 'oui'], $demandes->pluck('reponse')->all());
        $this->assertSame(['terminee'], $demandes->pluck('statut')->unique()->all());

        // État civil (majeur, vivant) et nationalité : le citoyen voit trois consultations
        $this->assertCount(3, $this->historiqueDe('ousmane', 'donnee.consultee'));
    }

    public function test_aucune_donnee_personnelle_n_est_conservee_apres_les_demarches(): void
    {
        $this->connecte('agent.mairie')
            ->postJson('/api/demarches/inscription-listes-electorales/lancer', ['citoyen' => ['identifiant' => 'BF-0001']])
            ->assertCreated();

        $tout = json_encode(\DB::table('verification_requests')->get())
            .json_encode(\DB::table('audit_logs')->get())
            .json_encode(\DB::table('attestations')->get());
        foreach (['1996-03-14', 'BF-0001', 'ILBOUDO', 'Ouagadougou'] as $secret) {
            $this->assertStringNotContainsString($secret, (string) $tout);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function historiqueDe(string $citoyen, string $type): array
    {
        return array_values(array_filter(
            $this->connecte($citoyen)->getJson('/api/citoyen/historique')->assertOk()->json('donnees'),
            fn ($l) => $l['type'] === $type,
        ));
    }
}
