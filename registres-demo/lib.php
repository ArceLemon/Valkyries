<?php

declare(strict_types=1);

/**
 * Registres de démonstration : trois faux registres (état civil, nationalité,
 * casier judiciaire) peuplés de citoyens FICTIFS.
 *
 * Cette classe est pure : elle reçoit une requête et un état de panne, et renvoie
 * [code HTTP, corps]. Le serveur (router.php) et les tests l'utilisent tous les deux.
 *
 * Format de réponse (volontairement différent d'un registre à l'autre, comme dans
 * la réalité), à adapter dans config/registres.php si le format du jury diffère :
 *   état civil  : { identifiant, identite: { nom, prenoms }, naissance: { date, lieu }, statut }
 *   nationalité : { identifiant, nationalite, certificat: { valide_jusqu } }
 *   casier      : { identifiant, condamnations: [ ... ] }
 */
final class RegistresDemo
{
    public const REGISTRES = ['etat-civil', 'nationalite', 'casier'];

    /**
     * @param  array<string, string>  $requete  paramètres d'URL
     * @param  array<string, string>  $entetes  en-têtes, clés en minuscules
     * @param  array<string, string>  $pannes   registre => ok | down | lent
     * @return array{0: int, 1: array<string, mixed>}
     */
    public static function repondre(string $methode, string $chemin, array $requete, array $entetes, array $pannes, string $fichierDonnees): array
    {
        $morceaux = array_values(array_filter(explode('/', trim($chemin, '/')), 'strlen'));

        if (($morceaux[0] ?? '') === 'admin') {
            return [404, ['erreur' => 'non_gere_ici']];
        }

        $registre = $morceaux[0] ?? '';
        if (! in_array($registre, self::REGISTRES, true)) {
            return [404, ['erreur' => 'registre_inconnu']];
        }

        $mode = $pannes[$registre] ?? 'ok';
        if ($mode === 'down') {
            return [503, ['erreur' => 'service_indisponible']];
        }
        if ($mode === 'lent') {
            sleep(2);
        }

        if (($morceaux[1] ?? '') === 'sante') {
            return [200, ['registre' => $registre, 'etat' => 'ok']];
        }

        // Authentification par clé d'API : demo-<registre>
        if (($entetes['x-api-key'] ?? '') !== 'demo-'.$registre) {
            return [401, ['erreur' => 'cle_api_invalide']];
        }

        $citoyens = json_decode((string) file_get_contents($fichierDonnees), true, 512, JSON_THROW_ON_ERROR);

        if (($morceaux[1] ?? '') === 'citoyens' && isset($morceaux[2])) {
            foreach ($citoyens as $c) {
                if ($c['identifiant'] === rawurldecode($morceaux[2])) {
                    return [200, self::projeter($registre, $c)];
                }
            }

            return [404, ['erreur' => 'citoyen_inconnu']];
        }

        // Recherche par attributs : uniquement l'état civil. Correspondance exacte
        // après normalisation ; la liste peut contenir plusieurs personnes.
        if ($registre === 'etat-civil' && ($morceaux[1] ?? '') === 'recherche') {
            $resultats = [];
            foreach ($citoyens as $c) {
                if (self::correspond($c, $requete)) {
                    $resultats[] = self::projeter($registre, $c);
                }
            }

            return [200, ['resultats' => $resultats]];
        }

        return [404, ['erreur' => 'route_inconnue']];
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    private static function projeter(string $registre, array $c): array
    {
        return match ($registre) {
            'etat-civil' => [
                'identifiant' => $c['identifiant'],
                'identite' => ['nom' => $c['nom'], 'prenoms' => $c['prenoms']],
                'naissance' => $c['naissance'],
                'statut' => $c['statut'],
                // Numéro déclaré : sert à confirmer l'inscription du citoyen (et, plus tard, l'USSD).
                'contact' => ['telephone' => $c['telephone'] ?? null],
            ],
            'nationalite' => [
                'identifiant' => $c['identifiant'],
                'nationalite' => $c['nationalite'],
                'certificat' => ['valide_jusqu' => $c['certificat_nationalite']],
            ],
            default => [
                'identifiant' => $c['identifiant'],
                'condamnations' => $c['condamnations'],
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $c
     * @param  array<string, string>  $requete
     */
    private static function correspond(array $c, array $requete): bool
    {
        $norm = static fn (string $v): string => trim((string) preg_replace('/[^A-Z0-9]+/', ' ', strtoupper(self::sansAccents($v))));

        if (isset($requete['nom']) && $norm($requete['nom']) !== $norm($c['nom'])) {
            return false;
        }
        if (isset($requete['prenoms']) && $norm($requete['prenoms']) !== $norm(implode(' ', $c['prenoms']))) {
            return false;
        }
        if (isset($requete['date_naissance']) && $requete['date_naissance'] !== $c['naissance']['date']) {
            return false;
        }
        if (isset($requete['lieu_naissance']) && $norm($requete['lieu_naissance']) !== $norm($c['naissance']['lieu'])) {
            return false;
        }

        return isset($requete['nom']);
    }

    private static function sansAccents(string $v): string
    {
        $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);

        return $t === false ? $v : $t;
    }
}
