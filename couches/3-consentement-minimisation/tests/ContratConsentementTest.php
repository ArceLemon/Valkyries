<?php

namespace Tests\Couches\Consentement;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ContratOpenApi;
use Tests\Concerns\UtiliseKuma;
use Tests\TestCase;

/**
 * Consentement et minimisation : le comportement réel suit
 * couches/3-consentement-minimisation/openapi.yaml.
 */
class ContratConsentementTest extends TestCase
{
    use ContratOpenApi, RefreshDatabase, UtiliseKuma;

    private const COUCHE = '3-consentement-minimisation';

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
        // --- Catalogue public : sans compte
        $catalogue = $this->appel(self::COUCHE, 'GET', '/api/catalogue', 200);
        $this->assertCount(7, $catalogue->json('donnees'));
        $this->appel(self::COUCHE, 'GET', '/api/catalogue/{code}', 200, null, '/api/catalogue/casier_vierge');
        $this->appel(self::COUCHE, 'GET', '/api/catalogue/{code}', 404, null, '/api/catalogue/inexistante');

        // --- Trois demandes de casier pour la même citoyenne : une acceptée puis retirée, une refusée, une acceptée
        $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0005'])->assertCreated();
        $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0005'])->assertCreated();

        $attente = $this->appel(self::COUCHE, 'GET', '/api/citoyen/demandes-a-valider', 200, null, null, 'fatimata');
        $this->assertGreaterThanOrEqual(2, count($attente->json('donnees')));
        $premier = $attente->json('donnees.0.id');
        $second = $attente->json('donnees.1.id');

        $this->appel(self::COUCHE, 'POST', '/api/citoyen/consentements/{id}/refuser', 200, null, "/api/citoyen/consentements/{$second}/refuser", 'fatimata');
        $this->appel(self::COUCHE, 'POST', '/api/citoyen/consentements/{id}/accepter', 200, null, "/api/citoyen/consentements/{$premier}/accepter", 'fatimata');
        $this->appel(self::COUCHE, 'POST', '/api/citoyen/consentements/{id}/retirer', 200, null, "/api/citoyen/consentements/{$premier}/retirer", 'fatimata');

        $decisions = $this->appel(self::COUCHE, 'GET', '/api/citoyen/consentements', 200, null, null, 'fatimata');
        $statuts = collect($decisions->json('donnees'))->pluck('statut')->sort()->values()->all();
        $this->assertSame(['refuse', 'retire'], $statuts);

        // --- Accord par code SMS, pour le citoyen sans smartphone
        $reference = $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0003'])->assertCreated()->json('reference');
        preg_match('/: (\d{6}) \(valable/', \App\Models\SmsEnvoye::latest('id')->firstOrFail()->message, $m);
        $chemin = "/api/demandes/{$reference}/code";
        $this->appel(self::COUCHE, 'POST', '/api/demandes/{reference}/code', 422, ['code' => '000000'], $chemin, 'agent.commerce');
        $this->appel(self::COUCHE, 'POST', '/api/demandes/{reference}/code', 422, ['code' => 'abc'], $chemin, 'agent.commerce');
        $this->appel(self::COUCHE, 'POST', '/api/demandes/{reference}/code', 404, ['code' => $m[1]], $chemin, 'agent.mairie');
        $this->appel(self::COUCHE, 'POST', '/api/demandes/{reference}/code', 200, ['code' => $m[1]], $chemin, 'agent.commerce');
        $this->appel(self::COUCHE, 'POST', '/api/demandes/{reference}/code', 409, ['code' => $m[1]], $chemin, 'agent.commerce');

        $bloquee = $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0005'])->assertCreated()->json('reference');
        $cheminBloque = "/api/demandes/{$bloquee}/code";
        foreach (['111111', '222222'] as $faux) {
            $this->appel(self::COUCHE, 'POST', '/api/demandes/{reference}/code', 422, ['code' => $faux], $cheminBloque, 'agent.commerce');
        }
        $this->appel(self::COUCHE, 'POST', '/api/demandes/{reference}/code', 423, ['code' => '333333'], $cheminBloque, 'agent.commerce');

        $expiree = $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0001'])->assertCreated()->json('reference');
        preg_match('/: (\d{6}) \(valable/', \App\Models\SmsEnvoye::latest('id')->firstOrFail()->message, $m2);
        $this->travel(16)->minutes();
        $this->appel(self::COUCHE, 'POST', '/api/demandes/{reference}/code', 410, ['code' => $m2[1]], "/api/demandes/{$expiree}/code", 'agent.commerce');

        $this->assertToutEstCouvert(self::COUCHE);
    }

    public function test_seule_une_reponse_fermee_sort_et_le_registre_n_est_pas_interroge_sans_accord(): void
    {
        $demande = $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0005'])->assertCreated();
        $this->assertSame('en_attente_consentement', $demande->json('statut'));
        $this->assertNull($demande->json('reponse'));

        // Refus : le registre n'est jamais interrogé, aucune réponse n'est produite
        $id = $this->connecte('fatimata')->getJson('/api/citoyen/demandes-a-valider')->json('donnees.0.id');
        $this->connecte('fatimata')->postJson("/api/citoyen/consentements/{$id}/refuser")->assertOk();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'donnee.consultee']);

        $vue = collect($this->connecte('agent.commerce')->getJson('/api/demandes')->json('donnees'))->first();
        $this->assertSame('refusee_citoyen', $vue['statut']);
        $this->assertNull($vue['reponse']);

        // Les réponses possibles du catalogue sont fermées
        foreach ($this->connecte('agent.commerce')->getJson('/api/catalogue')->json('donnees') as $question) {
            $this->assertSame(['oui', 'non', 'impossible'], $question['reponses']);
        }
    }

    public function test_un_citoyen_ne_decide_que_pour_lui_meme(): void
    {
        $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0005'])->assertCreated();
        $id = $this->connecte('fatimata')->getJson('/api/citoyen/demandes-a-valider')->json('donnees.0.id');

        $this->connecte('ousmane')->postJson("/api/citoyen/consentements/{$id}/accepter")->assertNotFound();
        $this->assertSame([], $this->connecte('ousmane')->getJson('/api/citoyen/demandes-a-valider')->json('donnees'));
        $this->connecte('agent.commerce')->getJson('/api/citoyen/demandes-a-valider')->assertForbidden();
    }
}
