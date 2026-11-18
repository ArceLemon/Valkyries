<?php

namespace Tests\Feature;

use App\Models\ApiCredential;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\UtiliseKuma;
use Tests\TestCase;

/**
 * Clés d'API par organisme et demandes signées : le logiciel d'une administration
 * appelle KUMA BANA sans passer par le portail, avec les mêmes règles.
 */
class CleApiTest extends TestCase
{
    use RefreshDatabase, UtiliseKuma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerKuma();
    }

    /**
     * @param  array<string, mixed>  $corps
     * @return array{0: string, 1: int, 2: string}  clé complète, id, identifiant public
     */
    private function creerCle(array $corps = []): array
    {
        $reponse = $this->connecte('responsable.commerce')
            ->postJson('/api/organisme/cles', $corps + ['nom' => 'Logiciel du registre du commerce']);
        $reponse->assertCreated();

        return [$reponse->json('cle'), $reponse->json('id'), $reponse->json('identifiant')];
    }

    /**
     * @param  array<string, mixed>  $corps
     * @param  array<string, string>  $entetes
     */
    private function api(?string $cle, string $methode, string $uri, array $corps = [], array $entetes = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->json($methode, $uri, $corps, ($cle === null ? [] : ['X-Api-Key' => $cle]) + $entetes);
    }

    /**
     * @return array<string, mixed>
     */
    private function demande(string $question = 'majeur'): array
    {
        return [
            'question' => $question,
            'finalite' => 'Immatriculation d\'une entreprise',
            'citoyen' => ['identifiant' => 'BF-0001'],
        ];
    }

    /**
     * @param  array<string, mixed>  $corps
     * @return array<string, string>
     */
    private function entetesSignes(string $secrete, string $methode, string $chemin, array $corps, ?int $horodatage = null, ?string $nonce = null): array
    {
        $horodatage ??= time();
        $nonce ??= bin2hex(random_bytes(12));
        $message = implode("\n", [(string) $horodatage, $nonce, strtoupper($methode), $chemin, hash('sha256', json_encode($corps))]);

        return [
            'X-Kuma-Horodatage' => (string) $horodatage,
            'X-Kuma-Nonce' => $nonce,
            'X-Kuma-Signature' => base64_encode(sodium_crypto_sign_detached($message, $secrete)),
        ];
    }

    public function test_une_cle_d_api_pose_une_demande_comme_le_portail(): void
    {
        [$cle, , $kid] = $this->creerCle();

        $reponse = $this->api($cle, 'POST', '/api/v1/demandes', $this->demande());

        $reponse->assertCreated()
            ->assertJsonPath('statut', 'terminee')
            ->assertJsonPath('reponse', 'oui');
        $this->assertStringNotContainsString('1996-03-14', $reponse->getContent());

        // Le journal garde l'origine de la demande
        $ligne = AuditLog::where('action', 'demande.creee')->firstOrFail();
        $this->assertSame('api', $ligne->details['canal']);
        $this->assertSame($kid, $ligne->details['cle']);
        $this->assertNotNull($ligne->organization_id);

        // La lecture se fait aussi par la clé
        $this->api($cle, 'GET', '/api/v1/demandes/'.$reponse->json('reference'))
            ->assertOk()->assertJsonPath('reponse', 'oui');
    }

    public function test_la_cle_n_est_montree_qu_une_fois_et_son_secret_n_est_pas_stocke(): void
    {
        [$cle] = $this->creerCle();
        $secret = substr($cle, -40);

        $liste = $this->connecte('responsable.commerce')->getJson('/api/organisme/cles')->assertOk();
        $this->assertStringNotContainsString($secret, $liste->getContent());
        $this->assertStringNotContainsString('secret', $liste->getContent());

        $this->assertStringNotContainsString($secret, json_encode(DB::table('api_credentials')->get()));
        $this->assertStringNotContainsString($secret, json_encode(DB::table('audit_logs')->get()));
    }

    public function test_cle_absente_ou_fausse_refusee(): void
    {
        [$cle] = $this->creerCle();

        $this->api(null, 'POST', '/api/v1/demandes', $this->demande())->assertUnauthorized();
        $this->api('n-importe-quoi', 'POST', '/api/v1/demandes', $this->demande())->assertUnauthorized();

        // Bon identifiant, mauvais secret
        $fausse = substr($cle, 0, -40).str_repeat('0', 40);
        $this->api($fausse, 'POST', '/api/v1/demandes', $this->demande())->assertUnauthorized();
    }

    public function test_la_cle_est_limitee_aux_questions_choisies(): void
    {
        [$cle] = $this->creerCle(['questions' => ['majeur']]);

        $this->api($cle, 'POST', '/api/v1/demandes', $this->demande('majeur'))->assertCreated();
        // L'organisme a le droit de poser cette question, mais pas cette clé
        $this->api($cle, 'POST', '/api/v1/demandes', $this->demande('nationalite_burkinabe'))->assertForbidden();
    }

    public function test_les_droits_de_l_organisme_s_appliquent_aussi_par_api(): void
    {
        [$cle] = $this->creerCle();

        $mauvaiseFinalite = ['finalite' => 'Autre finalité'] + $this->demande();
        $this->api($cle, 'POST', '/api/v1/demandes', $mauvaiseFinalite)->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'demande.refusee']);
    }

    public function test_le_casier_exige_le_consentement_aussi_par_api(): void
    {
        [$cle] = $this->creerCle();

        $this->api($cle, 'POST', '/api/v1/demandes', ['citoyen' => ['identifiant' => 'BF-0005']] + $this->demande('casier_vierge'))
            ->assertCreated()
            ->assertJsonPath('statut', 'en_attente_consentement')
            ->assertJsonPath('reponse', null);
    }

    public function test_la_revocation_de_la_cle_est_immediate(): void
    {
        [$cle, $id] = $this->creerCle();
        $this->api($cle, 'POST', '/api/v1/demandes', $this->demande())->assertCreated();

        $this->connecte('responsable.commerce')->postJson("/api/organisme/cles/{$id}/revoquer")->assertOk();

        $this->api($cle, 'POST', '/api/v1/demandes', $this->demande())->assertUnauthorized();
        $this->assertDatabaseHas('audit_logs', ['action' => 'cle.revoquee']);
    }

    public function test_une_cle_expiree_est_refusee(): void
    {
        [$cle] = $this->creerCle(['expire_le' => now()->addDay()->toDateTimeString()]);
        $this->api($cle, 'POST', '/api/v1/demandes', $this->demande())->assertCreated();

        $this->travel(2)->days();

        $this->api($cle, 'POST', '/api/v1/demandes', $this->demande())->assertUnauthorized();
    }

    public function test_le_quota_de_la_cle_est_respecte(): void
    {
        [$cle] = $this->creerCle(['quota_par_minute' => 2]);

        $this->api($cle, 'POST', '/api/v1/demandes', $this->demande())->assertCreated();
        $this->api($cle, 'POST', '/api/v1/demandes', $this->demande())->assertCreated();
        $this->api($cle, 'POST', '/api/v1/demandes', $this->demande())->assertStatus(429);
    }

    public function test_la_suspension_de_l_organisme_coupe_la_cle(): void
    {
        [$cle] = $this->creerCle();
        $organisme = Organization::where('sigle', 'RCCM-DEMO')->firstOrFail();

        $this->connecte('admin')->postJson("/api/admin/organisations/{$organisme->id}/suspendre")->assertOk();

        $this->api($cle, 'POST', '/api/v1/demandes', $this->demande())->assertForbidden();
    }

    public function test_une_cle_ne_lit_que_ses_propres_demandes(): void
    {
        [$cle] = $this->creerCle();

        // Une demande posée depuis le portail par un agent n'est pas visible par la clé
        $reference = $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->json('reference');

        $this->api($cle, 'GET', '/api/v1/demandes/'.$reference)->assertNotFound();
    }

    public function test_le_compte_technique_ne_se_connecte_pas_et_n_apparait_pas_dans_l_equipe(): void
    {
        [, , $kid] = $this->creerCle();
        $technique = User::where('email', "api-{$kid}@api.kumabana.invalid")->firstOrFail();
        $this->assertTrue($technique->technique);

        $this->postJson('/api/auth/connexion', [
            'email' => $technique->email,
            'mot_de_passe' => 'peu-importe',
        ])->assertUnauthorized();

        $equipe = $this->connecte('responsable.commerce')->getJson('/api/equipe')->assertOk();
        $this->assertStringNotContainsString('api.kumabana.invalid', $equipe->getContent());
    }

    public function test_seul_le_responsable_cree_des_cles_et_les_donnees_sont_validees(): void
    {
        $this->connecte('agent.commerce')->postJson('/api/organisme/cles', ['nom' => 'x'])->assertForbidden();
        $this->connecte('responsable.commerce')->postJson('/api/organisme/cles', [])->assertStatus(422);
        $this->connecte('responsable.commerce')
            ->postJson('/api/organisme/cles', ['nom' => 'x', 'questions' => ['inconnue']])->assertStatus(422);
        $this->connecte('responsable.commerce')
            ->postJson('/api/organisme/cles', ['nom' => 'x', 'cle_publique' => base64_encode('trop court')])->assertStatus(422);
    }

    public function test_une_cle_avec_signature_exige_des_demandes_signees(): void
    {
        $paire = sodium_crypto_sign_keypair();
        $secrete = sodium_crypto_sign_secretkey($paire);
        [$cle, , $kid] = $this->creerCle(['cle_publique' => base64_encode(sodium_crypto_sign_publickey($paire))]);

        $corps = $this->demande();
        $uri = '/api/v1/demandes';

        // Sans signature : refusé, même avec la bonne clé (une clé volée ne suffit plus)
        $this->api($cle, 'POST', $uri, $corps)->assertUnauthorized();

        // Avec une signature valide : accepté, et la signature est conservée comme preuve
        $entetes = $this->entetesSignes($secrete, 'POST', $uri, $corps);
        $this->api($cle, 'POST', $uri, $corps, $entetes)->assertCreated()->assertJsonPath('reponse', 'oui');

        $ligne = AuditLog::where('action', 'demande.creee')->latest('id')->firstOrFail();
        $this->assertSame($entetes['X-Kuma-Signature'], $ligne->details['signature']);
        $this->assertSame($kid, $ligne->details['cle']);
    }

    public function test_une_demande_signee_modifiee_est_refusee(): void
    {
        $paire = sodium_crypto_sign_keypair();
        $secrete = sodium_crypto_sign_secretkey($paire);
        [$cle] = $this->creerCle(['cle_publique' => base64_encode(sodium_crypto_sign_publickey($paire))]);

        $corps = $this->demande();
        $entetes = $this->entetesSignes($secrete, 'POST', '/api/v1/demandes', $corps);

        // Même signature, mais le contenu envoyé est différent
        $falsifie = ['citoyen' => ['identifiant' => 'BF-0005']] + $corps;
        $this->api($cle, 'POST', '/api/v1/demandes', $falsifie, $entetes)->assertUnauthorized();
    }

    public function test_une_signature_d_un_autre_organisme_est_refusee(): void
    {
        $paire = sodium_crypto_sign_keypair();
        [$cle] = $this->creerCle(['cle_publique' => base64_encode(sodium_crypto_sign_publickey($paire))]);

        $autre = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());
        $entetes = $this->entetesSignes($autre, 'POST', '/api/v1/demandes', $this->demande());

        $this->api($cle, 'POST', '/api/v1/demandes', $this->demande(), $entetes)->assertUnauthorized();
    }

    public function test_le_rejeu_d_une_demande_signee_est_refuse(): void
    {
        $paire = sodium_crypto_sign_keypair();
        $secrete = sodium_crypto_sign_secretkey($paire);
        [$cle] = $this->creerCle(['cle_publique' => base64_encode(sodium_crypto_sign_publickey($paire))]);

        $corps = $this->demande();
        $entetes = $this->entetesSignes($secrete, 'POST', '/api/v1/demandes', $corps);

        $this->api($cle, 'POST', '/api/v1/demandes', $corps, $entetes)->assertCreated();
        // Un attaquant qui a capté la requête la renvoie à l'identique
        $this->api($cle, 'POST', '/api/v1/demandes', $corps, $entetes)->assertUnauthorized();
        $this->assertSame(1, \App\Models\VerificationRequest::count());
    }

    public function test_une_demande_signee_trop_ancienne_est_refusee(): void
    {
        $paire = sodium_crypto_sign_keypair();
        $secrete = sodium_crypto_sign_secretkey($paire);
        [$cle] = $this->creerCle(['cle_publique' => base64_encode(sodium_crypto_sign_publickey($paire))]);

        $corps = $this->demande();
        $entetes = $this->entetesSignes($secrete, 'POST', '/api/v1/demandes', $corps, time() - 3600);

        $this->api($cle, 'POST', '/api/v1/demandes', $corps, $entetes)->assertUnauthorized();
    }

    public function test_les_cles_invalides_repetees_sont_freinees(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->api('kb_aaaaaaaaaaaa_'.str_repeat('b', 40), 'POST', '/api/v1/demandes', $this->demande())->assertUnauthorized();
        }

        $this->api('kb_aaaaaaaaaaaa_'.str_repeat('b', 40), 'POST', '/api/v1/demandes', $this->demande())->assertStatus(429);
    }

    public function test_la_liste_des_cles_montre_l_usage(): void
    {
        [$cle] = $this->creerCle();
        $this->api($cle, 'POST', '/api/v1/demandes', $this->demande())->assertCreated();

        $liste = $this->connecte('responsable.commerce')->getJson('/api/organisme/cles')->assertOk();

        $this->assertNotNull($liste->json('donnees.0.dernier_usage_le'));
        $this->assertFalse($liste->json('donnees.0.revoquee'));
        $this->assertFalse($liste->json('donnees.0.signature_obligatoire'));
        $this->assertSame(1, ApiCredential::count());
    }
}
