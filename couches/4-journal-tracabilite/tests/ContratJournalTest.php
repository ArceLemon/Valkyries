<?php

namespace Tests\Couches\Journal;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ContratOpenApi;
use Tests\Concerns\UtiliseKuma;
use Tests\TestCase;

/**
 * Journal de traçabilité : le comportement réel suit couches/4-journal-tracabilite/openapi.yaml.
 */
class ContratJournalTest extends TestCase
{
    use ContratOpenApi, RefreshDatabase, UtiliseKuma;

    private const COUCHE = '4-journal-tracabilite';

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
        $demande = $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();
        $jeton = $demande->json('attestation');
        $this->assertNotEmpty($jeton);

        $journal = $this->appel(self::COUCHE, 'GET', '/api/journal', 200, null, null, 'admin');
        $this->assertNotEmpty($journal->json('donnees'));
        $this->appel(self::COUCHE, 'GET', '/api/journal', 403, null, null, 'agent.commerce');

        $this->appel(self::COUCHE, 'POST', '/api/journal/points-de-controle', 201, null, null, 'admin');
        $this->appel(self::COUCHE, 'GET', '/api/journal/points-de-controle', 200);
        $verif = $this->appel(self::COUCHE, 'POST', '/api/journal/verifier-chaine', 200, null, null, 'admin');
        $this->assertTrue($verif->json('valide'));
        $this->assertGreaterThanOrEqual(1, $verif->json('points_de_controle'));

        $valide = $this->appel(self::COUCHE, 'POST', '/api/preuves/verifier', 200, ['jeton' => $jeton]);
        $this->assertSame('valide', $valide->json('etat'));
        $this->appel(self::COUCHE, 'GET', '/api/preuves/cles', 200);

        $this->assertToutEstCouvert(self::COUCHE);
    }

    public function test_une_attestation_modifiee_est_rejetee_et_ne_montre_aucune_donnee(): void
    {
        $jeton = (string) $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->json('attestation');

        $k = strlen($jeton) - 12;
        $faux = substr($jeton, 0, $k).($jeton[$k] === 'A' ? 'B' : 'A').substr($jeton, $k + 1);

        $reponse = $this->appel(self::COUCHE, 'POST', '/api/preuves/verifier', 200, ['jeton' => $faux]);
        $this->assertSame('alteree', $reponse->json('etat'));
        $this->assertNull($reponse->json('attestation'));
    }

    public function test_modifier_une_ligne_du_journal_est_detecte(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();
        $this->assertTrue($this->appel(self::COUCHE, 'POST', '/api/journal/verifier-chaine', 200, null, null, 'admin')->json('valide'));

        // Quelqu'un réécrit une ligne directement dans la base
        $ligne = AuditLog::query()->orderBy('id')->skip(2)->first();
        DB::table('audit_logs')->where('id', $ligne->id)->update(['resultat' => 'falsifie']);

        $verif = $this->appel(self::COUCHE, 'POST', '/api/journal/verifier-chaine', 200, null, null, 'admin');
        $this->assertFalse($verif->json('valide'));
        $this->assertSame($ligne->id, $verif->json('rupture.id'));
    }

    public function test_le_citoyen_n_apparait_que_sous_forme_d_empreinte(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();

        $texte = (string) $this->connecte('admin')->getJson('/api/journal')->getContent();
        foreach (['BF-0001', '1996-03-14', 'ILBOUDO', 'Ousmane'] as $secret) {
            $this->assertStringNotContainsString($secret, $texte);
        }
        foreach ($this->connecte('admin')->getJson('/api/journal')->json('donnees') as $ligne) {
            $this->assertTrue($ligne['sujet'] === null || strlen($ligne['sujet']) === 12);
        }
    }
}
