<?php

namespace Tests\Couches\EspaceUsager;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ContratOpenApi;
use Tests\Concerns\UtiliseKuma;
use Tests\TestCase;

/**
 * Espace usager : le comportement réel suit couches/5-espace-usager/openapi.yaml.
 * Cette API est celle du portail web et de l'application mobile.
 */
class ContratEspaceUsagerTest extends TestCase
{
    use ContratOpenApi, RefreshDatabase, UtiliseKuma;

    private const COUCHE = '5-espace-usager';

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
        // Une consultation et une preuve à retrouver
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();

        $this->appel(self::COUCHE, 'POST', '/api/auth/connexion', 401, ['email' => 'ousmane@demo.kumabana.test', 'mot_de_passe' => 'mauvais']);
        $connexion = $this->appel(self::COUCHE, 'POST', '/api/auth/connexion', 200, ['email' => 'ousmane@demo.kumabana.test', 'mot_de_passe' => 'kumabana-demo']);
        $this->assertSame('citoyen', $connexion->json('utilisateur.role'));
        $entetes = ['Authorization' => 'Bearer '.$connexion->json('jeton')];

        $this->appel(self::COUCHE, 'GET', '/api/auth/moi', 200, null, null, null, $entetes);
        $this->appel(self::COUCHE, 'GET', '/api/auth/moi', 401);

        $historique = $this->appel(self::COUCHE, 'GET', '/api/citoyen/historique', 200, null, null, null, $entetes);
        $this->assertSame('donnee.consultee', $historique->json('donnees.0.type'));
        $this->assertSame('Registre du commerce (démo)', $historique->json('donnees.0.organisme'));

        $preuves = $this->appel(self::COUCHE, 'GET', '/api/citoyen/preuves', 200, null, null, null, $entetes);
        $this->assertCount(1, $preuves->json('donnees'));
        $this->appel(self::COUCHE, 'GET', '/api/citoyen/preuves', 403, null, null, 'agent.commerce');

        // Le casier attend l'accord : un message prévient le citoyen
        $this->poser('agent.commerce', 'casier_vierge', ['identifiant' => 'BF-0001'])->assertCreated();
        $messages = $this->appel(self::COUCHE, 'GET', '/api/citoyen/messages', 200, null, null, null, $entetes);
        $this->assertCount(1, $messages->json('donnees'));
        $this->appel(self::COUCHE, 'GET', '/api/citoyen/messages', 403, null, null, 'agent.commerce');

        config(['kuma.push.url' => 'http://ntfy']);
        $abonnement = $this->appel(self::COUCHE, 'GET', '/api/citoyen/notifications/abonnement', 200, null, null, null, $entetes);
        $this->assertTrue($abonnement->json('actif'));
        $this->appel(self::COUCHE, 'GET', '/api/citoyen/notifications/abonnement', 403, null, null, 'agent.commerce');

        $this->appel(self::COUCHE, 'POST', '/api/auth/deconnexion', 200, null, null, null, $entetes);

        // Un citoyen sans compte crée le sien : identifiant, nom, prénoms, téléphone, mot de passe
        $kabore = ['identifiant' => 'BF-0004', 'nom' => 'KABORE', 'prenoms' => 'Issouf', 'telephone' => '+226 70 00 00 04', 'mot_de_passe' => 'secret-kabore'];
        $this->appel(self::COUCHE, 'POST', '/api/auth/inscription', 422, ['telephone' => '+226 70 00 00 01'] + $kabore);
        $this->appel(self::COUCHE, 'POST', '/api/auth/inscription', 201, $kabore);
        $this->appel(self::COUCHE, 'POST', '/api/auth/inscription', 409, $kabore);
        $this->appel(self::COUCHE, 'POST', '/api/auth/connexion', 200, ['identifiant' => 'BF-0004', 'mot_de_passe' => 'secret-kabore']);
        $this->pannes['etat-civil'] = 'down';
        $this->appel(self::COUCHE, 'POST', '/api/auth/inscription', 503, ['identifiant' => 'BF-0006', 'nom' => 'ZONGO', 'prenoms' => 'Adama', 'telephone' => '70000006', 'mot_de_passe' => 'secret-zongo']);
        $this->pannes = [];

        $this->assertToutEstCouvert(self::COUCHE);
    }

    public function test_le_citoyen_retrouve_chaque_consultation_sans_voir_la_donnee(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();
        $this->poser('agent.commerce', 'nationalite_burkinabe', ['identifiant' => 'BF-0001'])->assertCreated();

        $historique = $this->appel(self::COUCHE, 'GET', '/api/citoyen/historique', 200, null, null, 'ousmane');
        $this->assertCount(2, $historique->json('donnees'));

        foreach (['1996', 'BF-0001', 'ILBOUDO'] as $secret) {
            $this->assertStringNotContainsString($secret, (string) $historique->getContent());
        }

        // Un autre citoyen ne voit rien de tout cela
        $autre = $this->appel(self::COUCHE, 'GET', '/api/citoyen/historique', 200, null, null, 'fatimata');
        $this->assertSame([], $autre->json('donnees'));
    }
}
