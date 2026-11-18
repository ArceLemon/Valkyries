<?php

namespace Tests\Feature;

use App\Kuma\Journal\Journal;
use App\Models\AuditLog;
use App\Models\JournalCheckpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Concerns\UtiliseKuma;
use Tests\TestCase;

/**
 * Points de contrôle signés : ils empêchent de réécrire tout l'historique, même par
 * quelqu'un qui sait recalculer toutes les empreintes de la chaîne.
 */
class PointsDeControleTest extends TestCase
{
    use RefreshDatabase, UtiliseKuma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerKuma();
    }

    /**
     * Simule un attaquant qui a accès à la base : il modifie une ligne PUIS recalcule
     * toutes les empreintes de la chaîne pour que celle-ci reste cohérente.
     *
     * @param  array<string, mixed>  $changement
     */
    private function reecrireToutelaChaine(int $idModifie, array $changement): void
    {
        DB::table('audit_logs')->where('id', $idModifie)->update($changement);

        $precedent = str_repeat('0', 64);
        foreach (AuditLog::query()->orderBy('id')->get() as $ligne) {
            $hash = Journal::calculer($precedent, [
                'horodatage' => $ligne->horodatage,
                'acteur_type' => $ligne->acteur_type,
                'acteur_id' => $ligne->acteur_id,
                'organization_id' => $ligne->organization_id,
                'action' => $ligne->action,
                'objet_type' => $ligne->objet_type,
                'objet_ref' => $ligne->objet_ref,
                'sujet_pseudo' => $ligne->sujet_pseudo,
                'details' => $ligne->details ?? [],
                'resultat' => $ligne->resultat,
            ]);
            DB::table('audit_logs')->where('id', $ligne->id)->update(['prev_hash' => $precedent, 'hash' => $hash]);
            $precedent = $hash;
        }
    }

    private function deuxDemandes(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();
        $this->poser('agent.commerce', 'nationalite_burkinabe', ['identifiant' => 'BF-0001'])->assertCreated();
    }

    public function test_un_point_de_controle_est_signe_puis_verifie(): void
    {
        $this->deuxDemandes();

        $this->artisan('kuma:point-de-controle')->assertExitCode(0);

        $this->assertSame(1, JournalCheckpoint::count());
        $verdict = app(Journal::class)->verifier();
        $this->assertTrue($verdict['valide']);
        $this->assertSame(1, $verdict['points_de_controle']);

        // Rien n'a changé : pas de nouveau point
        $this->artisan('kuma:point-de-controle')->assertExitCode(0);
        $this->assertSame(1, JournalCheckpoint::count());

        // Après de nouvelles écritures : un second point, chaîné au premier
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0005'])->assertCreated();
        $this->artisan('kuma:point-de-controle');
        $this->assertSame(2, JournalCheckpoint::count());
        $this->assertTrue(app(Journal::class)->verifier()['valide']);
    }

    public function test_une_reecriture_complete_de_la_chaine_est_detectee(): void
    {
        $this->deuxDemandes();
        $this->artisan('kuma:point-de-controle');

        $milieu = AuditLog::orderBy('id')->skip(1)->firstOrFail();
        $this->reecrireToutelaChaine($milieu->id, ['resultat' => 'falsifie']);

        $verdict = app(Journal::class)->verifier();
        $this->assertFalse($verdict['valide']);
        $this->assertSame('point_de_controle_incoherent', $verdict['rupture']['motif']);
    }

    public function test_sans_point_de_controle_une_reecriture_complete_passerait_inapercue(): void
    {
        // Limite documentée : c'est précisément ce que les points de contrôle corrigent.
        $this->deuxDemandes();

        $milieu = AuditLog::orderBy('id')->skip(1)->firstOrFail();
        $this->reecrireToutelaChaine($milieu->id, ['resultat' => 'falsifie']);

        $this->assertTrue(app(Journal::class)->verifier()['valide']);
    }

    public function test_un_point_de_controle_falsifie_est_detecte(): void
    {
        $this->deuxDemandes();
        $this->artisan('kuma:point-de-controle');

        // L'attaquant remplace l'empreinte annoncée dans le point, mais ne peut pas re-signer
        $point = JournalCheckpoint::firstOrFail();
        [$charge, $signature] = explode('.', $point->jeton);
        $json = base64_decode(strtr($charge, '-_', '+/'));
        $faux = str_replace($point->hash, str_repeat('a', 64), $json);
        $jeton = rtrim(strtr(base64_encode($faux), '+/', '-_'), '=').'.'.$signature;
        DB::table('journal_checkpoints')->where('id', $point->id)->update(['jeton' => $jeton]);

        $verdict = app(Journal::class)->verifier();
        $this->assertFalse($verdict['valide']);
        $this->assertSame('point_de_controle_signature_invalide', $verdict['rupture']['motif']);
    }

    public function test_supprimer_un_point_au_milieu_est_detecte(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();
        $this->artisan('kuma:point-de-controle');
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0005'])->assertCreated();
        $this->artisan('kuma:point-de-controle');
        $this->poser('agent.commerce', 'nationalite_burkinabe', ['identifiant' => 'BF-0001'])->assertCreated();
        $this->artisan('kuma:point-de-controle');
        $this->assertSame(3, JournalCheckpoint::count());

        DB::table('journal_checkpoints')->where('id', JournalCheckpoint::orderBy('id')->skip(1)->value('id'))->delete();

        $verdict = app(Journal::class)->verifier();
        $this->assertFalse($verdict['valide']);
        $this->assertSame('point_de_controle_chainage_rompu', $verdict['rupture']['motif']);
    }

    public function test_supprimer_les_dernieres_lignes_couvertes_est_detecte(): void
    {
        $this->deuxDemandes();
        $this->artisan('kuma:point-de-controle');

        // La chaîne reste cohérente sans sa dernière ligne, mais le point la désigne
        DB::table('audit_logs')->where('id', JournalCheckpoint::firstOrFail()->dernier_id)->delete();

        $verdict = app(Journal::class)->verifier();
        $this->assertFalse($verdict['valide']);
        $this->assertSame('point_de_controle_ligne_absente', $verdict['rupture']['motif']);
    }

    public function test_les_points_sont_publics_et_sans_donnee_de_citoyen(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();
        $this->artisan('kuma:point-de-controle');

        $this->app['auth']->forgetGuards();
        $reponse = $this->getJson('/api/journal/points-de-controle')->assertOk();

        $reponse->assertJsonCount(1, 'donnees');
        $this->assertNotEmpty($reponse->json('donnees.0.hash'));
        $this->assertNotEmpty($reponse->json('donnees.0.jeton'));
        $this->assertStringNotContainsString('BF-0001', $reponse->getContent());
        $this->assertStringNotContainsString('1996', $reponse->getContent());
    }

    public function test_l_administrateur_peut_signer_un_point_a_la_demande(): void
    {
        $this->deuxDemandes();

        $this->connecte('admin')->postJson('/api/journal/points-de-controle')
            ->assertCreated()->assertJsonPath('cree', true);
        // Les écritures de l'admin eux-mêmes (aucune ici) mises à part : rien de nouveau
        $this->connecte('admin')->postJson('/api/journal/points-de-controle')
            ->assertOk()->assertJsonPath('cree', false);

        // Un agent ne peut pas signer de point de contrôle
        $this->connecte('agent.commerce')->postJson('/api/journal/points-de-controle')->assertForbidden();
    }

    public function test_un_point_ne_se_modifie_ni_ne_se_supprime_par_l_application(): void
    {
        $this->deuxDemandes();
        $this->artisan('kuma:point-de-controle');
        $point = JournalCheckpoint::firstOrFail();

        $this->expectException(LogicException::class);
        $point->update(['hash' => 'autre']);
    }
}
