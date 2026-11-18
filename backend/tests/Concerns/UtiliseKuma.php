<?php

namespace Tests\Concerns;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/**
 * Prépare un environnement de test complet : base de démonstration, clés de
 * signature temporaires, et registres simulés en mémoire (aucun serveur réel).
 */
trait UtiliseKuma
{
    /** @var array<string, string> registre => ok | down | lent */
    protected array $pannes = [];

    protected function preparerKuma(): void
    {
        $this->seed(DemoSeeder::class);

        config(['kuma.cles.dossier' => sys_get_temp_dir().'/kuma-tests-'.bin2hex(random_bytes(4))]);

        require_once base_path('../registres-demo/lib.php');

        Http::fake(function (Request $requete) {
            $url = parse_url($requete->url());

            // Serveur ntfy simulé (notifications push) : il accepte tout.
            if (($url['host'] ?? '') === 'ntfy') {
                return Http::response(['id' => 'test', 'event' => 'message'], 200);
            }
            parse_str($url['query'] ?? '', $requeteUrl);

            [$code, $corps] = \RegistresDemo::repondre(
                'GET',
                $url['path'] ?? '/',
                $requeteUrl,
                ['x-api-key' => $requete->header('X-Api-Key')[0] ?? ''],
                $this->pannes,
                base_path('../registres-demo/citoyens.json'),
            );

            return Http::response($corps, $code);
        });
    }

    protected function utilisateur(string $alias): User
    {
        return User::where('email', $alias.'@demo.kumabana.test')->firstOrFail();
    }

    protected function connecte(string $alias): static
    {
        Sanctum::actingAs($this->utilisateur($alias), ['*']);

        return $this;
    }

    /**
     * @param  array<string, string>  $citoyen
     */
    protected function poser(string $alias, string $question, array $citoyen, string $finalite = 'Immatriculation d\'une entreprise', ?string $parametre = null): TestResponse
    {
        $this->connecte($alias);

        return $this->postJson('/api/demandes', array_filter([
            'question' => $question,
            'parametre' => $parametre,
            'finalite' => $finalite,
            'citoyen' => $citoyen,
        ]));
    }

    /**
     * Connexion réelle par l'API : renvoie le jeton, sans état partagé entre requêtes.
     */
    protected function jeton(string $alias): string
    {
        $reponse = $this->postJson('/api/auth/connexion', [
            'email' => $alias.'@demo.kumabana.test',
            'mot_de_passe' => DemoSeeder::MOT_DE_PASSE,
        ]);
        $reponse->assertOk();

        return (string) $reponse->json('jeton');
    }
}
