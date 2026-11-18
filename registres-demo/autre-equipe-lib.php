<?php

declare(strict_types=1);


final class RegistresAutreEquipe
{
    public const JETON = 'tok-autre-equipe';

    private const PAYS = ['BF' => 'BFA', 'CI' => 'CIV', 'ML' => 'MLI', 'NE' => 'NER', 'GH' => 'GHA', 'TG' => 'TGO'];

    /**
     * @param  array<string, string>  $requete  paramètres d'URL
     * @param  array<string, string>  $entetes  en-têtes, clés en minuscules
     * @return array{0: int, 1: array<string, mixed>}
     */
    public static function repondre(string $methode, string $chemin, array $requete, array $entetes, string $fichierDonnees): array
    {
        $chemin = '/'.trim($chemin, '/');

        if ($chemin === '/v2/health') {
            return [200, ['status' => 'up', 'version' => '2.1']];
        }

        if (($entetes['authorization'] ?? '') !== 'Bearer '.self::JETON) {
            return [401, ['error' => 'unauthorized']];
        }
        if ($methode !== 'GET') {
            return [405, ['error' => 'method_not_allowed']];
        }

        $routes = [
            '/v2/civil/persons' => 'civil',
            '/v2/nationality/holders' => 'nationality',
            '/v2/judicial/records' => 'judicial',
        ];
        if (! isset($routes[$chemin])) {
            return [404, ['error' => 'unknown_route']];
        }

        $nin = (string) ($requete['nin'] ?? '');
        if ($nin === '') {
            return [400, ['error' => 'nin_required']];
        }

        $citoyens = json_decode((string) file_get_contents($fichierDonnees), true, 512, JSON_THROW_ON_ERROR);
        $liste = [];
        foreach ($citoyens as $c) {
            if ($c['identifiant'] === $nin) {
                $liste[] = self::projeter($routes[$chemin], $c);
            }
        }

        // Ici, « introuvable » n'est pas une erreur 404 : c'est une liste vide.
        return [200, ['data' => $liste, 'count' => count($liste)]];
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    private static function projeter(string $type, array $c): array
    {
        return match ($type) {
            'civil' => [
                'nin' => $c['identifiant'],
                'surname' => $c['nom'],
                'given_names' => $c['prenoms'],
                'born' => ['on' => $c['naissance']['date'], 'at' => $c['naissance']['lieu']],
                'life_status' => $c['statut'] === 'vivant' ? 'ALIVE' : 'DECEASED',
            ],
            'nationality' => [
                'nin' => $c['identifiant'],
                'citizenship' => self::PAYS[$c['nationalite']] ?? $c['nationalite'],
                'certificate' => ['expires' => $c['certificat_nationalite']],
            ],
            default => [
                'nin' => $c['identifiant'],
                'convictions' => $c['condamnations'],
            ],
        };
    }
}
