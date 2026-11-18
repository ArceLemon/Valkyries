<?php

namespace Tests\Feature;

use App\Kuma\Journal\Journal;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Concerns\UtiliseKuma;
use Tests\TestCase;

class JournalEtPreuvesTest extends TestCase
{
    use RefreshDatabase, UtiliseKuma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerKuma();
    }

    public function test_la_chaine_du_journal_est_integre(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();
        $this->poser('agent.commerce', 'nationalite_burkinabe', ['identifiant' => 'BF-0001'])->assertCreated();

        $this->artisan('kuma:verifier-journal')->assertExitCode(0);
        $this->assertTrue(app(Journal::class)->verifier()['valide']);
    }

    public function test_modifier_une_ligne_est_detecte(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();
        $milieu = AuditLog::orderBy('id')->skip(1)->firstOrFail();

        // Altération directe en base, hors de l'application
        DB::table('audit_logs')->where('id', $milieu->id)->update(['resultat' => 'falsifie']);

        $verdict = app(Journal::class)->verifier();
        $this->assertFalse($verdict['valide']);
        $this->assertSame($milieu->id, $verdict['rupture']['id']);
        $this->assertSame('contenu_modifie', $verdict['rupture']['motif']);
        $this->artisan('kuma:verifier-journal')->assertExitCode(1);
    }

    public function test_supprimer_une_ligne_est_detecte(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();
        $milieu = AuditLog::orderBy('id')->skip(1)->firstOrFail();

        DB::table('audit_logs')->where('id', $milieu->id)->delete();

        $verdict = app(Journal::class)->verifier();
        $this->assertFalse($verdict['valide']);
        $this->assertSame('chainage_rompu', $verdict['rupture']['motif']);
    }

    public function test_l_administrateur_verifie_la_chaine_par_l_api(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();

        $this->connecte('admin')->postJson('/api/journal/verifier-chaine')
            ->assertOk()->assertJsonPath('valide', true);

        // Première ligne réelle : sous PostgreSQL, les identifiants ne repartent pas de 1 à chaque test
        $premiere = (int) DB::table('audit_logs')->min('id');
        DB::table('audit_logs')->where('id', $premiere)->update(['action' => 'falsifie']);

        $this->connecte('auditeur')->postJson('/api/journal/verifier-chaine')
            ->assertOk()->assertJsonPath('valide', false)->assertJsonPath('rupture.id', $premiere);
    }

    public function test_l_application_ne_modifie_ni_ne_supprime_une_ligne(): void
    {
        $ligne = app(Journal::class)->ecrire('test.ecriture');

        $this->expectException(LogicException::class);
        $ligne->update(['action' => 'autre']);
    }

    public function test_une_donnee_brute_est_refusee_dans_le_journal(): void
    {
        $this->expectException(LogicException::class);
        app(Journal::class)->ecrire('test.fuite', ['details' => ['date_naissance' => '1996-03-14']]);
    }

    public function test_une_donnee_brute_imbriquee_est_refusee(): void
    {
        $this->expectException(LogicException::class);
        app(Journal::class)->ecrire('test.fuite', ['details' => ['contexte' => ['nom' => 'ILBOUDO']]]);
    }

    public function test_l_administrateur_ne_voit_aucun_identifiant_dans_le_journal(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();

        $journal = $this->connecte('admin')->getJson('/api/journal')->assertOk();

        foreach (['BF-0001', '1996-03-14', 'ILBOUDO'] as $secret) {
            $this->assertStringNotContainsString($secret, $journal->getContent());
        }
    }

    public function test_attestation_valide_puis_altere_puis_expiree(): void
    {
        $jeton = $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->json('attestation');

        // Sans aucun compte : n'importe qui peut vérifier
        $this->app['auth']->forgetGuards();
        $ok = $this->postJson('/api/preuves/verifier', ['jeton' => $jeton])->assertOk();
        $ok->assertJsonPath('etat', 'valide')
            ->assertJsonPath('attestation.reponse', 'oui')
            ->assertJsonPath('attestation.question', 'Est majeur')
            ->assertJsonPath('attestation.demandeur', 'Registre du commerce (démo)');
        // La vérification ne révèle aucune donnée du citoyen
        $this->assertStringNotContainsString('BF-0001', $ok->getContent());

        // Un seul mot modifié dans la charge utile : la signature ne correspond plus
        [$charge, $signature] = explode('.', $jeton);
        $json = base64_decode(strtr($charge, '-_', '+/'));
        $altere = rtrim(strtr(base64_encode(str_replace('"reponse":"oui"', '"reponse":"non"', $json)), '+/', '-_'), '=');
        $this->postJson('/api/preuves/verifier', ['jeton' => $altere.'.'.$signature])
            ->assertJsonPath('etat', 'alteree')
            ->assertJsonPath('attestation', null);

        $this->postJson('/api/preuves/verifier', ['jeton' => 'nimportequoi'])->assertJsonPath('etat', 'alteree');

        // Après la durée de validité (24 h pour « majeur »)
        $this->travel(2)->days();
        $this->postJson('/api/preuves/verifier', ['jeton' => $jeton])->assertJsonPath('etat', 'expiree');
    }

    public function test_les_cles_publiques_sont_publiees_et_la_rotation_garde_les_anciennes(): void
    {
        $ancienJeton = $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->json('attestation');
        $ancienne = $this->getJson('/api/preuves/cles')->json('active');

        $this->artisan('kuma:generer-cles')->assertExitCode(0);

        $cles = $this->getJson('/api/preuves/cles')->assertOk();
        $this->assertNotSame($ancienne, $cles->json('active'));
        $this->assertCount(2, $cles->json('cles'));
        $this->assertSame('Ed25519', $cles->json('cles.0.algorithme'));

        // Une attestation émise avant la rotation reste vérifiable
        $this->postJson('/api/preuves/verifier', ['jeton' => $ancienJeton])->assertJsonPath('etat', 'valide');
    }
}
