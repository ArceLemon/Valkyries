<?php

namespace Tests\Couches\Passerelle;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\UtiliseKuma;
use Tests\TestCase;

/**
 * Épreuve « brancher le registre d'une autre équipe sur notre passerelle ».
 *
 * Les trois registres de l'autre équipe ont un format différent du nôtre (jeton Bearer,
 * recherche par ?nin=, réponse en liste, champs en anglais, nationalité « BFA »).
 * On les branche avec un seul profil de configuration : le moteur ne change pas, et
 * les réponses sont identiques à celles obtenues avec nos propres registres.
 */
class InteroperabiliteAutreEquipeTest extends TestCase
{
    use RefreshDatabase, UtiliseKuma;

    private const FINALITE = 'Immatriculation d\'une entreprise';

    /** @var list<string> */
    private array $urlsAppelees = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerKuma();
        require_once base_path('../registres-demo/autre-equipe-lib.php');

        // Le profil de l'autre équipe remplace nos trois registres
        config(['registres' => array_merge(config('registres'), require base_path('profils-registres/autre-equipe.php'))]);

        // On remplace la simulation de nos registres par celle de l'autre équipe
        Http::swap(new Factory());
        Http::fake(function (Request $requete) {
            $url = parse_url($requete->url());
            parse_str($url['query'] ?? '', $parametres);
            $this->urlsAppelees[] = $requete->url();

            [$code, $corps] = \RegistresAutreEquipe::repondre(
                'GET',
                $url['path'] ?? '/',
                $parametres,
                ['authorization' => $requete->header('Authorization')[0] ?? ''],
                base_path('../registres-demo/citoyens.json'),
            );

            return Http::response($corps, $code);
        });
    }

    public function test_les_registres_de_l_autre_equipe_repondent_comme_les_notres(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])
            ->assertCreated()->assertJsonPath('reponse', 'oui');

        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0002'])
            ->assertCreated()->assertJsonPath('reponse', 'non');

        // Nationalité : « BFA » et « CIV » sont traduits en « BF » et « CI » par la configuration
        $this->poser('agent.commerce', 'nationalite_burkinabe', ['identifiant' => 'BF-0001'])
            ->assertCreated()->assertJsonPath('reponse', 'oui');
        $this->poser('agent.commerce', 'nationalite_burkinabe', ['identifiant' => 'BF-0004'])
            ->assertCreated()->assertJsonPath('reponse', 'non');

        // « ALIVE » et « DECEASED » sont traduits en « vivant » et « decede »
        $this->poser('agent.mairie', 'vivant', ['identifiant' => 'BF-0001'], 'Délivrance d\'un document')
            ->assertCreated()->assertJsonPath('reponse', 'oui');
        $this->poser('agent.mairie', 'vivant', ['identifiant' => 'BF-0006'], 'Délivrance d\'un document')
            ->assertCreated()->assertJsonPath('reponse', 'non');

        // On a bien parlé au registre de l'autre équipe, pas au nôtre
        $this->assertNotEmpty($this->urlsAppelees);
        foreach ($this->urlsAppelees as $url) {
            $this->assertStringContainsString('127.0.0.1:8082/v2/', $url);
        }
        $this->assertStringContainsString('nin=BF-0001', implode(' ', $this->urlsAppelees));
    }

    public function test_une_liste_vide_est_un_citoyen_introuvable_jamais_un_non(): void
    {
        $this->poser('agent.commerce', 'nationalite_burkinabe', ['identifiant' => 'BF-9999'])
            ->assertCreated()
            ->assertJsonPath('reponse', 'impossible')
            ->assertJsonPath('motif', 'introuvable');
    }

    public function test_le_consentement_et_la_minimisation_restent_les_memes(): void
    {
        $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0005'])
            ->assertCreated()
            ->assertJsonPath('statut', 'en_attente_consentement');
        $this->assertSame([], $this->urlsAppelees, 'Le registre ne doit pas être interrogé avant l\'accord du citoyen.');

        $consentement = $this->connecte('fatimata')->getJson('/api/citoyen/demandes-a-valider')->assertOk()->json('donnees.0.id');
        $this->connecte('fatimata')->postJson("/api/citoyen/consentements/{$consentement}/accepter")->assertOk();

        $this->connecte('agent.commerce')->getJson('/api/demandes')->assertOk()
            ->assertJsonPath('donnees.0.reponse', 'oui');
        $this->assertNotEmpty($this->urlsAppelees);
    }

    public function test_un_jeton_refuse_met_la_demande_en_attente_sans_inventer_de_reponse(): void
    {
        config(['registres.etat_civil.auth.valeur' => 'mauvais-jeton']);

        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])
            ->assertCreated()
            ->assertJsonPath('statut', 'en_attente_registre')
            ->assertJsonPath('reponse', null);
    }

    public function test_la_sante_du_registre_de_l_autre_equipe_est_surveillee(): void
    {
        $this->artisan('kuma:sonder-registres')->assertSuccessful();

        $this->connecte('agent.commerce')->getJson('/api/registres')->assertOk()
            ->assertJsonPath('donnees.0.etat', 'en_service');
    }
}
