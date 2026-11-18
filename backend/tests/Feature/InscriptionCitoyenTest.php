<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UtiliseKuma;
use Tests\TestCase;

/**
 * Un citoyen crée lui-même son compte : identifiant, nom, prénoms, téléphone,
 * mot de passe. L'identité et le numéro sont confrontés à l'état civil, sans rien conserver
 * du registre.
 */
class InscriptionCitoyenTest extends TestCase
{
    use RefreshDatabase, UtiliseKuma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerKuma();
    }

    /**
     * @param  array<string, string>  $autres
     * @return array<string, string>
     */
    private function kabore(array $autres = []): array
    {
        // BF-0004 n'a pas de compte ; l'état civil connaît son numéro : +226 70 00 00 04
        return $autres + [
            'identifiant' => 'bf-0004',
            'nom' => 'Kaboré',
            'prenoms' => 'issouf',
            'telephone' => '70 00 00 04',
            'mot_de_passe' => 'secret-kabore',
        ];
    }

    public function test_inscription_puis_connexion_par_identifiant(): void
    {
        $reponse = $this->postJson('/api/auth/inscription', $this->kabore())
            ->assertCreated()
            ->assertJsonPath('utilisateur.role', 'citoyen')
            ->assertJsonPath('utilisateur.identifiant', 'BF-0004')
            ->assertJsonPath('utilisateur.email', null);

        // Le jeton reçu ouvre directement l'espace citoyen
        $this->withToken($reponse->json('jeton'))->getJson('/api/citoyen/historique')->assertOk();
        $this->app['auth']->forgetGuards();

        // Plus tard : connexion avec l'identifiant et le mot de passe
        $this->postJson('/api/auth/connexion', ['identifiant' => 'BF-0004', 'mot_de_passe' => 'secret-kabore'])
            ->assertOk()
            ->assertJsonPath('utilisateur.identifiant', 'BF-0004');
        $this->postJson('/api/auth/connexion', ['identifiant' => 'BF-0004', 'mot_de_passe' => 'mauvais'])
            ->assertUnauthorized();

        $this->assertDatabaseHas('audit_logs', ['action' => 'compte.inscription', 'resultat' => 'cree']);
    }

    public function test_le_numero_doit_etre_celui_de_l_etat_civil(): void
    {
        // Le numéro de quelqu'un d'autre, ou un identifiant inconnu : même réponse
        $this->postJson('/api/auth/inscription', $this->kabore(['telephone' => '+226 70 00 00 01']))->assertStatus(422);
        $this->postJson('/api/auth/inscription', $this->kabore(['identifiant' => 'BF-9999']))->assertStatus(422);

        $this->assertSame(0, User::where('identifiant', 'BF-0004')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'compte.inscription_refusee']);

        // Écrit autrement, c'est le même numéro : accepté
        $this->postJson('/api/auth/inscription', $this->kabore(['telephone' => '0022670000004']))->assertCreated();
    }

    public function test_le_nom_et_les_prenoms_doivent_etre_ceux_de_l_etat_civil(): void
    {
        $this->postJson('/api/auth/inscription', $this->kabore(['nom' => 'OUEDRAOGO']))->assertStatus(422);
        $this->postJson('/api/auth/inscription', $this->kabore(['prenoms' => 'Ousmane']))->assertStatus(422);
        $this->assertSame(0, User::where('identifiant', 'BF-0004')->count());

        // Accents et casse ne comptent pas ; le compte porte le nom saisi
        $this->postJson('/api/auth/inscription', $this->kabore(['nom' => 'kabore', 'prenoms' => 'ISSOUF']))
            ->assertCreated()
            ->assertJsonPath('utilisateur.nom', 'ISSOUF Kabore');
    }

    public function test_une_personne_decedee_ne_peut_pas_etre_inscrite(): void
    {
        // BF-0006 est décédé dans le registre de démonstration : son identifiant ne sert plus
        $this->postJson('/api/auth/inscription', ['identifiant' => 'BF-0006', 'nom' => 'ZONGO', 'prenoms' => 'Adama', 'telephone' => '70000006', 'mot_de_passe' => 'secret-zongo'])
            ->assertStatus(422);
    }

    public function test_un_identifiant_ne_sert_qu_a_un_compte(): void
    {
        // BF-0001 a déjà un compte (Ousmane)
        $this->postJson('/api/auth/inscription', $this->kabore([
            'identifiant' => 'BF-0001', 'telephone' => '+226 70 00 00 01',
        ]))->assertStatus(409);
    }

    public function test_registre_en_panne_ne_cree_rien(): void
    {
        $this->pannes['etat-civil'] = 'down';

        $this->postJson('/api/auth/inscription', $this->kabore())->assertStatus(503);
        $this->assertSame(0, User::where('identifiant', 'BF-0004')->count());
    }

    public function test_aucune_donnee_du_registre_n_est_conservee_ni_journalisee(): void
    {
        $this->postJson('/api/auth/inscription', $this->kabore())->assertCreated();

        $journal = json_encode(DB::table('audit_logs')->get());
        foreach (['BF-0004', 'KABORE', '1975-05-09', '70 00 00 04', '70000004'] as $secret) {
            $this->assertStringNotContainsString($secret, $journal);
        }
        $comptes = json_encode(DB::table('users')->get());
        foreach (['KABORE', '1975-05-09'] as $secret) {
            $this->assertStringNotContainsString($secret, $comptes);
        }
    }

    public function test_une_demande_anterieure_attend_le_nouveau_compte(): void
    {
        // Le casier exige l'accord du citoyen ; KABORE n'a pas encore de compte
        $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0004'])
            ->assertCreated()
            ->assertJsonPath('statut', 'en_attente_consentement');

        $jeton = $this->postJson('/api/auth/inscription', $this->kabore())->json('jeton');
        $this->app['auth']->forgetGuards();

        // Dès son inscription, il retrouve la demande et peut décider
        $this->withToken($jeton)->getJson('/api/citoyen/demandes-a-valider')
            ->assertOk()
            ->assertJsonCount(1, 'donnees');
    }

    public function test_validation_des_champs(): void
    {
        $this->postJson('/api/auth/inscription', $this->kabore(['mot_de_passe' => 'court']))
            ->assertStatus(422)->assertJsonValidationErrors('mot_de_passe');
        $this->postJson('/api/auth/inscription', ['identifiant' => 'BF-0004', 'nom' => 'KABORE', 'prenoms' => 'Issouf', 'mot_de_passe' => 'secret-kabore'])
            ->assertStatus(422)->assertJsonValidationErrors('telephone');
        $this->postJson('/api/auth/inscription', ['identifiant' => 'BF-0004', 'telephone' => '70000004', 'mot_de_passe' => 'secret-kabore'])
            ->assertStatus(422)->assertJsonValidationErrors(['nom', 'prenoms']);
    }

    public function test_les_agents_se_connectent_toujours_par_email(): void
    {
        $this->postJson('/api/auth/connexion', ['email' => 'agent.commerce@demo.kumabana.test', 'mot_de_passe' => 'kumabana-demo'])
            ->assertOk();
        $this->postJson('/api/auth/connexion', ['mot_de_passe' => 'kumabana-demo'])->assertStatus(422);
    }
}
