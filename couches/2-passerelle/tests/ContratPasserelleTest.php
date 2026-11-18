<?php

namespace Tests\Couches\Passerelle;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ContratOpenApi;
use Tests\Concerns\UtiliseKuma;
use Tests\TestCase;

/**
 * La passerelle d'échange fait ce que dit couches/2-passerelle/openapi.yaml.
 */
class ContratPasserelleTest extends TestCase
{
    use ContratOpenApi, RefreshDatabase, UtiliseKuma;

    private const COUCHE = '2-passerelle';

    private const FINALITE = 'Immatriculation d\'une entreprise';

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerKuma();
    }

    public function test_les_operations_documentees_existent(): void
    {
        $this->assertOperationsExistent(self::COUCHE);
    }

    public function test_toutes_les_operations_respectent_le_contrat(): void
    {
        $majeur = ['question' => 'majeur', 'finalite' => self::FINALITE, 'citoyen' => ['identifiant' => 'BF-0001']];

        // --- Logiciel d'une administration : clé d'API
        $cle = $this->connecte('responsable.commerce')->postJson('/api/organisme/cles', ['nom' => 'Logiciel du registre du commerce'])
            ->assertCreated()->json('cle');
        $entetes = ['X-Api-Key' => $cle];

        $cree = $this->appel(self::COUCHE, 'POST', '/api/v1/demandes', 201, $majeur, null, null, $entetes);
        $this->assertSame('oui', $cree->json('reponse'));
        $this->assertSame('terminee', $cree->json('statut'));

        $this->appel(self::COUCHE, 'GET', '/api/v1/demandes/{reference}', 200, null, '/api/v1/demandes/'.$cree->json('reference'), null, $entetes);
        $this->appel(self::COUCHE, 'GET', '/api/v1/demandes/{reference}', 404, null, '/api/v1/demandes/inconnue', null, $entetes);
        $this->appel(self::COUCHE, 'POST', '/api/v1/demandes', 401, $majeur);
        $this->appel(self::COUCHE, 'POST', '/api/v1/demandes', 422, [], null, null, $entetes);

        // --- Guichet de l'agent
        $this->appel(self::COUCHE, 'POST', '/api/demandes', 201, $majeur, null, 'agent.commerce');
        $this->appel(self::COUCHE, 'GET', '/api/demandes', 200, null, null, 'agent.commerce');
        // La mairie n'a aucun droit sur le casier : refus avant toute interrogation de registre
        $this->appel(self::COUCHE, 'POST', '/api/demandes', 403, ['question' => 'casier_vierge', 'finalite' => 'Délivrance d\'un document', 'citoyen' => ['identifiant' => 'BF-0001']], null, 'agent.mairie');

        $this->appel(self::COUCHE, 'GET', '/api/demarches', 200, null, null, 'agent.commerce');
        $this->appel(self::COUCHE, 'POST', '/api/demarches/{code}/lancer', 201, ['citoyen' => ['identifiant' => 'BF-0005']], '/api/demarches/creation-entreprise/lancer', 'agent.commerce');

        // --- Résilience
        $this->artisan('kuma:sonder-registres')->assertSuccessful();
        $registres = $this->appel(self::COUCHE, 'GET', '/api/registres', 200, null, null, 'agent.commerce');
        $this->assertCount(3, $registres->json('donnees'));

        $this->assertToutEstCouvert(self::COUCHE);
    }

    public function test_la_reponse_ne_contient_jamais_la_fiche_du_registre(): void
    {
        $reponse = $this->appel(self::COUCHE, 'POST', '/api/demandes', 201, [
            'question' => 'majeur',
            'finalite' => self::FINALITE,
            'citoyen' => ['identifiant' => 'BF-0001'],
        ], null, 'agent.commerce');

        $texte = $reponse->getContent();
        foreach (['1996', 'ILBOUDO', 'Ouagadougou', 'Ousmane'] as $secret) {
            $this->assertStringNotContainsString($secret, (string) $texte);
        }
    }
}
