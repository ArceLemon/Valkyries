<?php

namespace Tests\Couches\Registres;

use Tests\Concerns\ContratOpenApi;
use Tests\TestCase;

/**
 * Les registres de démonstration respectent le contrat de couches/1-registres/openapi.yaml.
 * C'est ce contrat qu'un autre registre doit suivre (ou décrire dans un profil de la passerelle).
 */
class ContratRegistresTest extends TestCase
{
    use ContratOpenApi;

    private const COUCHE = '1-registres';

    /** @var array<string, true> */
    private array $verifiees = [];

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('../registres-demo/lib.php');
    }

    /**
     * @param  array<string, string>  $requete
     * @param  array<string, string>  $pannes
     * @return array<string, mixed>
     */
    private function appeler(string $chemin, string $cle, int $codeAttendu, string $uriDocumentee, array $requete = [], array $pannes = []): array
    {
        [$code, $corps] = \RegistresDemo::repondre(
            'GET',
            $chemin,
            $requete,
            $cle === '' ? [] : ['x-api-key' => $cle],
            $pannes,
            base_path('../registres-demo/citoyens.json'),
        );

        $this->assertSame($codeAttendu, $code, "GET {$chemin} : code {$codeAttendu} attendu");
        $this->assertConforme(self::COUCHE, 'GET', $uriDocumentee, $code, $corps);
        $this->verifiees['GET '.$uriDocumentee] = true;

        return $corps;
    }

    public function test_les_registres_respectent_le_contrat(): void
    {
        foreach (['etat-civil', 'nationalite', 'casier'] as $registre) {
            $this->appeler("/{$registre}/sante", '', 200, "/{$registre}/sante");
        }
        $this->appeler('/etat-civil/sante', '', 503, '/etat-civil/sante', [], ['etat-civil' => 'down']);

        $civil = $this->appeler('/etat-civil/citoyens/BF-0001', 'demo-etat-civil', 200, '/etat-civil/citoyens/{identifiant}');
        $this->assertSame('ILBOUDO', $civil['identite']['nom']);
        $this->appeler('/etat-civil/citoyens/BF-9999', 'demo-etat-civil', 404, '/etat-civil/citoyens/{identifiant}');
        $this->appeler('/etat-civil/citoyens/BF-0001', 'mauvaise-cle', 401, '/etat-civil/citoyens/{identifiant}');

        $liste = $this->appeler('/etat-civil/recherche', 'demo-etat-civil', 200, '/etat-civil/recherche', ['nom' => 'Ilboudo']);
        $this->assertCount(2, $liste['resultats'], 'Deux homonymes : la passerelle refusera de deviner.');

        $this->appeler('/nationalite/citoyens/BF-0004', 'demo-nationalite', 200, '/nationalite/citoyens/{identifiant}');
        $this->appeler('/nationalite/citoyens/BF-9999', 'demo-nationalite', 404, '/nationalite/citoyens/{identifiant}');

        $casier = $this->appeler('/casier/citoyens/BF-0003', 'demo-casier', 200, '/casier/citoyens/{identifiant}');
        $this->assertCount(1, $casier['condamnations']);
        $this->appeler('/casier/citoyens/BF-9999', 'demo-casier', 404, '/casier/citoyens/{identifiant}');

        // Toutes les opérations documentées ont été vérifiées contre le vrai comportement
        $documentees = array_map(fn (array $o) => $o[0].' '.$o[1], $this->operations(self::COUCHE));
        $this->assertSame([], array_values(array_diff($documentees, array_keys($this->verifiees))));
    }

    public function test_un_registre_ne_renvoie_que_sa_part_de_la_fiche(): void
    {
        $civil = $this->appeler('/etat-civil/citoyens/BF-0003', 'demo-etat-civil', 200, '/etat-civil/citoyens/{identifiant}');
        $this->assertArrayNotHasKey('condamnations', $civil);
        $this->assertArrayNotHasKey('nationalite', $civil);

        $casier = $this->appeler('/casier/citoyens/BF-0003', 'demo-casier', 200, '/casier/citoyens/{identifiant}');
        $this->assertArrayNotHasKey('identite', $casier);
        $this->assertArrayNotHasKey('naissance', $casier);
    }
}
