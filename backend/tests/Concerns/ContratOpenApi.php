<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

/**
 * Compare les spécifications OpenAPI des couches (dossier couches/) au comportement réel :
 *  - chaque opération documentée existe vraiment dans le routeur ;
 *  - les réponses réelles respectent les schémas documentés.
 *
 * Le validateur couvre ce qu'utilisent nos spécifications : type, nullable, required,
 * properties, items, enum, format date-time, et les références internes ($ref).
 */
trait ContratOpenApi
{
    /** @var array<string, array<string, mixed>> */
    private array $contrats = [];

    /** @var array<string, array<string, true>> opérations déjà vérifiées, par couche */
    private array $couvert = [];

    /**
     * @return array<string, mixed>
     */
    protected function contrat(string $couche): array
    {
        return $this->contrats[$couche] ??= Yaml::parseFile(base_path('../couches/'.$couche.'/openapi.yaml'));
    }

    /**
     * @return list<array{0: string, 1: string}>  [méthode, chemin] de chaque opération documentée
     */
    protected function operations(string $couche): array
    {
        $liste = [];
        foreach ((array) $this->contrat($couche)['paths'] as $chemin => $methodes) {
            foreach (array_keys((array) $methodes) as $methode) {
                if (in_array($methode, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    $liste[] = [strtoupper($methode), (string) $chemin];
                }
            }
        }

        return $liste;
    }

    /**
     * Toute opération documentée doit exister dans le routeur Laravel.
     */
    protected function assertOperationsExistent(string $couche, string $prefixeServeur = ''): void
    {
        $routes = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->methods() as $methode) {
                $routes[$methode.' /'.ltrim($route->uri(), '/')] = true;
            }
        }

        $this->assertNotEmpty($this->operations($couche), 'Le contrat ne documente aucune opération.');
        foreach ($this->operations($couche) as [$methode, $chemin]) {
            $this->assertArrayHasKey(
                $methode.' '.$prefixeServeur.$chemin,
                $routes,
                "L'opération documentée {$methode} {$chemin} n'existe pas dans l'API.",
            );
        }
    }

    /**
     * Fait un vrai appel, vérifie le code HTTP, compare la réponse au contrat et note
     * que l'opération a été couverte.
     *
     * @param  array<string, mixed>|null  $corps
     * @param  array<string, string>  $entetes
     * @param  string|null  $uri  adresse réelle quand le chemin documenté contient des {paramètres}
     * @param  string|null  $alias  utilisateur de démonstration connecté ; sans lui, appel anonyme
     */
    protected function appel(
        string $couche,
        string $methode,
        string $chemin,
        int $codeAttendu,
        ?array $corps = null,
        ?string $uri = null,
        ?string $alias = null,
        array $entetes = [],
    ): \Illuminate\Testing\TestResponse {
        $this->app['auth']->forgetGuards();
        if ($alias !== null) {
            $this->connecte($alias);
        }

        $reponse = $this->json($methode, $uri ?? $chemin, $corps ?? [], $entetes);
        $this->assertSame(
            $codeAttendu,
            $reponse->getStatusCode(),
            "{$methode} {$chemin} : code {$codeAttendu} attendu, ".$reponse->getStatusCode().' reçu : '.$reponse->getContent(),
        );

        $this->assertConforme($couche, $methode, $chemin, $codeAttendu, $reponse->json());
        $this->couvert[$couche][strtoupper($methode).' '.$chemin] = true;

        return $reponse;
    }

    /**
     * Chaque opération documentée a été vérifiée au moins une fois contre le comportement réel.
     */
    protected function assertToutEstCouvert(string $couche): void
    {
        $documentees = array_map(fn (array $o) => $o[0].' '.$o[1], $this->operations($couche));
        $this->assertSame([], array_values(array_diff($documentees, array_keys($this->couvert[$couche] ?? []))), 'Opérations documentées mais jamais vérifiées.');
    }

    /**
     * La réponse réelle doit respecter le schéma documenté pour ce code HTTP.
     *
     * @param  mixed  $corps  contenu JSON décodé
     */
    protected function assertConforme(string $couche, string $methode, string $chemin, int $code, mixed $corps): void
    {
        $contrat = $this->contrat($couche);
        $operation = $contrat['paths'][$chemin][strtolower($methode)] ?? null;
        $this->assertNotNull($operation, "Opération non documentée : {$methode} {$chemin}");

        $reponse = $operation['responses'][(string) $code] ?? $operation['responses'][$code] ?? null;
        $this->assertNotNull($reponse, "Réponse {$code} non documentée pour {$methode} {$chemin}");

        $schema = $reponse['content']['application/json']['schema'] ?? null;
        if ($schema === null) {
            return;
        }

        $erreurs = [];
        $this->valider($contrat, $schema, $corps, '$', $erreurs);
        $this->assertSame([], $erreurs, "Réponse non conforme au contrat ({$methode} {$chemin}) :\n".implode("\n", $erreurs));
    }

    /**
     * @param  array<string, mixed>  $contrat
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $erreurs
     */
    private function valider(array $contrat, array $schema, mixed $valeur, string $chemin, array &$erreurs): void
    {
        if (isset($schema['$ref'])) {
            $cible = $contrat;
            foreach (explode('/', ltrim((string) $schema['$ref'], '#/')) as $morceau) {
                $cible = $cible[$morceau] ?? [];
            }
            $this->valider($contrat, (array) $cible, $valeur, $chemin, $erreurs);

            return;
        }

        if ($valeur === null) {
            if (($schema['nullable'] ?? false) !== true) {
                $erreurs[] = "{$chemin} : null n'est pas autorisé";
            }

            return;
        }

        $type = $schema['type'] ?? null;
        $correspond = match ($type) {
            'object' => is_array($valeur) && ($valeur === [] || ! array_is_list($valeur)),
            'array' => is_array($valeur) && ($valeur === [] || array_is_list($valeur)),
            'string' => is_string($valeur),
            'integer' => is_int($valeur),
            'number' => is_int($valeur) || is_float($valeur),
            'boolean' => is_bool($valeur),
            default => true,
        };
        if (! $correspond) {
            $erreurs[] = "{$chemin} : type attendu « {$type} »";

            return;
        }

        if (isset($schema['enum']) && ! in_array($valeur, $schema['enum'], true)) {
            $erreurs[] = "{$chemin} : « ".json_encode($valeur)." » n'est pas dans ".json_encode($schema['enum']);
        }
        if (($schema['format'] ?? null) === 'date-time' && is_string($valeur) && strtotime($valeur) === false) {
            $erreurs[] = "{$chemin} : date-heure invalide";
        }

        if ($type === 'object') {
            foreach ((array) ($schema['required'] ?? []) as $cle) {
                if (! array_key_exists($cle, $valeur)) {
                    $erreurs[] = "{$chemin} : le champ « {$cle} » est obligatoire";
                }
            }
            foreach ((array) ($schema['properties'] ?? []) as $cle => $sousSchema) {
                if (array_key_exists($cle, $valeur)) {
                    $this->valider($contrat, (array) $sousSchema, $valeur[$cle], "{$chemin}.{$cle}", $erreurs);
                }
            }
        }

        if ($type === 'array' && isset($schema['items'])) {
            foreach (array_values($valeur) as $i => $element) {
                $this->valider($contrat, (array) $schema['items'], $element, "{$chemin}[{$i}]", $erreurs);
            }
        }
    }
}
