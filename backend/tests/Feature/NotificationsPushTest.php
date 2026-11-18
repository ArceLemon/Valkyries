<?php

namespace Tests\Feature;

use App\Kuma\Notifications\Push;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\UtiliseKuma;
use Tests\TestCase;

/**
 * Notifications push par ntfy auto-hébergé : un sujet secret par citoyen,
 * et aucune donnée personnelle dans le message.
 */
class NotificationsPushTest extends TestCase
{
    use RefreshDatabase, UtiliseKuma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerKuma();
        config(['kuma.push.url' => 'http://ntfy']);
    }

    /** @return list<Request> */
    private function pushes(): array
    {
        return Http::recorded(fn (Request $r) => parse_url($r->url(), PHP_URL_HOST) === 'ntfy')
            ->map(fn ($paire) => $paire[0])
            ->values()
            ->all();
    }

    public function test_demande_d_accord_envoie_un_push_au_seul_citoyen_concerne(): void
    {
        // Le casier exige l'accord d'Ousmane (BF-0001)
        $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0001'])
            ->assertJsonPath('statut', 'en_attente_consentement');

        $pushes = $this->pushes();
        $this->assertCount(1, $pushes);

        $sujet = app(Push::class)->sujet($this->utilisateur('ousmane'));
        $this->assertSame('http://ntfy/'.$sujet, $pushes[0]->url());
        $this->assertSame('high', $pushes[0]->header('Priority')[0]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'notification.envoyee', 'resultat' => 'envoye']);
    }

    public function test_le_message_ne_contient_aucune_donnee(): void
    {
        $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0001']);

        $push = $this->pushes()[0];
        $tout = $push->url().' '.$push->body().' '.json_encode($push->headers());
        foreach (['BF-0001', 'ILBOUDO', 'Ousmane', 'casier', 'Registre du commerce', 'RCCM'] as $secret) {
            $this->assertStringNotContainsStringIgnoringCase($secret, $tout);
        }
    }

    public function test_consultation_sur_base_legale_previent_le_citoyen(): void
    {
        // « majeur » est sur base légale : réponse immédiate, le citoyen est informé
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertJsonPath('reponse', 'oui');

        $this->assertCount(1, $this->pushes());
        $this->assertSame('consultation', $this->pushes()[0]->header('Tags')[0]);
    }

    public function test_citoyen_sans_compte_ou_push_desactive_rien_n_est_envoye(): void
    {
        // BF-0004 n'a pas de compte
        $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0004']);
        $this->assertCount(0, $this->pushes());

        config(['kuma.push.url' => '']);
        $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0001']);
        $this->assertCount(0, $this->pushes());
    }

    public function test_un_ntfy_en_panne_ne_bloque_pas_la_demande(): void
    {
        config(['kuma.push.url' => 'http://127.0.0.1:1']);

        $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0001'])
            ->assertCreated()
            ->assertJsonPath('statut', 'en_attente_consentement');
    }

    public function test_abonnement_reserve_au_citoyen_et_sujets_distincts(): void
    {
        $ousmane = $this->connecte('ousmane')->getJson('/api/citoyen/notifications/abonnement')
            ->assertOk()
            ->assertJsonPath('actif', true)
            ->json();
        $this->assertMatchesRegularExpression('/^kb-[a-f0-9]{32}$/', $ousmane['sujet']);
        $this->assertSame('/notifications/'.$ousmane['sujet'].'/json', $ousmane['chemin']);

        $aminata = $this->connecte('aminata')->getJson('/api/citoyen/notifications/abonnement')->json('sujet');
        $this->assertNotSame($ousmane['sujet'], $aminata);

        $this->connecte('agent.commerce')->getJson('/api/citoyen/notifications/abonnement')->assertForbidden();
    }
}
