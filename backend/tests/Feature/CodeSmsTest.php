<?php

namespace Tests\Feature;

use App\Models\Consent;
use App\Models\SmsEnvoye;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UtiliseKuma;
use Tests\TestCase;

/**
 * Accord par code SMS : le citoyen sans smartphone donne à l'agent le code reçu.
 */
class CodeSmsTest extends TestCase
{
    use RefreshDatabase, UtiliseKuma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerKuma();
    }

    /** @return array{0: string, 1: string} référence de la demande et code lu dans le SMS */
    private function demandeAvecCode(string $identifiant = 'BF-0005'): array
    {
        $reference = $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => $identifiant])
            ->assertCreated()->assertJsonPath('code_attendu', true)->json('reference');
        preg_match('/: (\d{6}) \(valable/', SmsEnvoye::latest('id')->firstOrFail()->message, $m);

        return [$reference, $m[1]];
    }

    public function test_le_bon_code_vaut_accord_et_la_reponse_arrive(): void
    {
        [$reference, $code] = $this->demandeAvecCode();

        $this->connecte('agent.commerce')->postJson("/api/demandes/$reference/code", ['code' => $code])
            ->assertOk()
            ->assertJsonPath('statut', 'terminee')
            ->assertJsonPath('reponse', 'oui')
            ->assertJsonPath('consentement', 'accepte')
            ->assertJsonPath('code_attendu', false);

        $this->assertDatabaseHas('audit_logs', ['action' => 'consentement.accepte', 'resultat' => 'accepte']);
        $this->assertStringContainsString('code_sms', \DB::table('audit_logs')->where('action', 'consentement.accepte')->value('details'));
    }

    public function test_le_code_n_est_jamais_dans_la_reponse_ni_en_clair_en_base(): void
    {
        [$reference, $code] = $this->demandeAvecCode();

        $reponse = $this->connecte('agent.commerce')->getJson("/api/demandes/$reference")->assertOk();
        $this->assertStringNotContainsString($code, $reponse->getContent());
        $this->assertStringNotContainsString($code, json_encode(\DB::table('consents')->get()));
        $this->assertStringNotContainsString($code, json_encode(\DB::table('audit_logs')->get()));
    }

    public function test_un_mauvais_code_est_refuse_puis_la_demande_se_bloque(): void
    {
        [$reference] = $this->demandeAvecCode();
        $this->connecte('agent.commerce');

        $this->postJson("/api/demandes/$reference/code", ['code' => '000000'])->assertStatus(422);
        $this->postJson("/api/demandes/$reference/code", ['code' => '000001'])->assertStatus(422);
        $this->postJson("/api/demandes/$reference/code", ['code' => '000002'])->assertStatus(423);

        $this->getJson("/api/demandes/$reference")->assertJsonPath('code_attendu', false)
            ->assertJsonPath('statut', 'en_attente_consentement');
        $this->assertDatabaseMissing('audit_logs', ['action' => 'donnee.consultee']);
    }

    public function test_apres_blocage_meme_le_bon_code_est_refuse(): void
    {
        [$reference, $code] = $this->demandeAvecCode();
        $this->connecte('agent.commerce');
        foreach (['000000', '000001', '000002'] as $faux) {
            $this->postJson("/api/demandes/$reference/code", ['code' => $faux]);
        }

        $this->postJson("/api/demandes/$reference/code", ['code' => $code])->assertStatus(423);
    }

    public function test_un_code_expire_est_refuse(): void
    {
        [$reference, $code] = $this->demandeAvecCode();
        $this->travel(16)->minutes();

        $this->connecte('agent.commerce')->postJson("/api/demandes/$reference/code", ['code' => $code])->assertStatus(410);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'donnee.consultee']);
    }

    public function test_le_code_d_une_autre_demande_est_refuse(): void
    {
        [$referenceA] = $this->demandeAvecCode('BF-0005');
        [, $codeB] = $this->demandeAvecCode('BF-0003');

        $this->connecte('agent.commerce')->postJson("/api/demandes/$referenceA/code", ['code' => $codeB])->assertStatus(422);
    }

    public function test_un_code_ne_sert_qu_une_fois(): void
    {
        [$reference, $code] = $this->demandeAvecCode();
        $this->connecte('agent.commerce')->postJson("/api/demandes/$reference/code", ['code' => $code])->assertOk();

        $this->postJson("/api/demandes/$reference/code", ['code' => $code])->assertStatus(409);
    }

    public function test_le_format_du_code_est_controle(): void
    {
        [$reference] = $this->demandeAvecCode();

        $this->connecte('agent.commerce')->postJson("/api/demandes/$reference/code", ['code' => '12ab'])->assertUnprocessable();
        $this->postJson("/api/demandes/$reference/code", [])->assertUnprocessable();
    }

    public function test_un_autre_organisme_ne_peut_pas_valider_la_demande(): void
    {
        [$reference, $code] = $this->demandeAvecCode();

        $this->connecte('agent.mairie')->postJson("/api/demandes/$reference/code", ['code' => $code])->assertNotFound();
        $this->assertSame('en_attente', Consent::firstOrFail()->statut);
    }

    public function test_pas_de_code_pour_un_citoyen_sans_numero(): void
    {
        $this->utilisateur('aminata')->update(['telephone' => null]);

        $reference = $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0003'])
            ->assertCreated()->assertJsonPath('code_attendu', false)->json('reference');

        $this->connecte('agent.commerce')->postJson("/api/demandes/$reference/code", ['code' => '123456'])->assertStatus(409);
    }

    public function test_le_citoyen_lit_son_code_dans_ses_messages_et_l_agent_ne_le_voit_pas(): void
    {
        $reference = $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0005'])
            ->assertCreated()->json('reference');

        // Dans l'application du citoyen, l'écran Messages montre le SMS avec le code
        $message = $this->connecte('fatimata')->getJson('/api/citoyen/messages')->assertOk()->json('donnees.0.message');
        $this->assertMatchesRegularExpression('/: (\d{6}) \(valable/', $message);

        // L'agent, lui, ne peut pas lire les messages du citoyen
        $this->connecte('agent.commerce')->getJson('/api/citoyen/messages')->assertForbidden();
        $vue = $this->getJson("/api/demandes/$reference")->assertOk()->getContent();
        preg_match('/: (\d{6}) \(valable/', $message, $m);
        $this->assertStringNotContainsString($m[1], $vue);
    }
}
