<?php

namespace Tests\Feature;

use App\Models\SmsEnvoye;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UtiliseKuma;
use Tests\TestCase;

/**
 * Le citoyen est prévenu qu'un organisme attend son accord, sans que le message
 * ne révèle la moindre donnée.
 */
class NotificationCitoyenTest extends TestCase
{
    use RefreshDatabase, UtiliseKuma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerKuma();
    }

    public function test_une_demande_en_attente_envoie_un_message_au_citoyen(): void
    {
        $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0005'])->assertCreated();

        $sms = SmsEnvoye::firstOrFail();
        $this->assertSame($this->utilisateur('fatimata')->id, $sms->user_id);
        $this->assertSame($this->utilisateur('fatimata')->telephone, $sms->destinataire);
        $this->assertStringContainsString('Registre du commerce', $sms->message);
        $this->assertStringContainsString('Casier judiciaire vierge', $sms->message);
        $this->assertDatabaseHas('audit_logs', ['action' => 'notification.envoyee']);
    }

    public function test_le_message_ne_contient_aucune_donnee_personnelle(): void
    {
        $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0005'])->assertCreated();

        $message = SmsEnvoye::firstOrFail()->message;
        $this->assertStringNotContainsString('BF-0005', $message);
        $this->assertDoesNotMatchRegularExpression('/\d{4}-\d{2}-\d{2}/', $message);
    }

    public function test_le_numero_est_chiffre_en_base(): void
    {
        $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0005'])->assertCreated();

        $brut = \DB::table('sms_envoyes')->value('destinataire');
        $this->assertStringNotContainsString('+226', (string) $brut);
        $this->assertStringNotContainsString('+226', (string) \DB::table('users')->where('identifiant', 'BF-0005')->value('telephone'));
    }

    public function test_pas_de_message_sans_compte_ni_numero(): void
    {
        // BF-0004 n'a pas de compte : personne à prévenir
        $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0004'])->assertCreated();
        $this->assertSame(0, SmsEnvoye::count());

        // Un compte sans numéro n'est pas prévenu, mais la demande l'attend dans l'application
        $this->utilisateur('aminata')->update(['telephone' => null]);
        $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0003'])
            ->assertCreated()->assertJsonPath('statut', 'en_attente_consentement');
        $this->assertSame(0, SmsEnvoye::count());
    }

    public function test_on_peut_couper_les_messages(): void
    {
        config(['kuma.notifications.canal' => 'aucun']);
        $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0005'])->assertCreated();
        $this->assertSame(0, SmsEnvoye::count());
    }

    public function test_une_question_sans_consentement_n_envoie_rien(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0005'])->assertCreated();
        $this->assertSame(0, SmsEnvoye::count());
    }

    public function test_le_citoyen_ne_lit_que_ses_messages(): void
    {
        $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0005'])->assertCreated();

        $this->connecte('fatimata')->getJson('/api/citoyen/messages')
            ->assertOk()->assertJsonCount(1, 'donnees')
            ->assertJsonPath('donnees.0.canal', 'simule');

        $this->connecte('aminata')->getJson('/api/citoyen/messages')
            ->assertOk()->assertJsonCount(0, 'donnees');

        $this->connecte('agent.commerce')->getJson('/api/citoyen/messages')->assertForbidden();
    }
}
