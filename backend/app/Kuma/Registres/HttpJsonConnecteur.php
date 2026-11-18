<?php

namespace App\Kuma\Registres;

use App\Kuma\Support\Normalisation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Connecteur générique pour un registre exposé en API web JSON.
 * Tout se règle dans config/registres.php : adresse, authentification,
 * mode de recherche et correspondance des champs.
 */
final class HttpJsonConnecteur implements ConnecteurRegistre
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly string $nom,
        private readonly array $config,
    ) {}

    public function rechercher(Recherche $recherche): ResultatRegistre
    {
        $mode = (string) Arr::get($this->config, 'lookup.mode', 'identifiant');
        $debut = hrtime(true);

        if ($mode === 'attributs') {
            return $this->parAttributs($recherche, $debut);
        }

        if ($recherche->identifiant === null || $recherche->identifiant === '') {
            return ResultatRegistre::introuvable();
        }

        $chemin = str_replace(
            '{identifiant}',
            rawurlencode($recherche->identifiant),
            (string) Arr::get($this->config, 'lookup.chemin', '/citoyens/{identifiant}'),
        );

        // Certains registres cherchent par paramètre d'URL plutôt que par chemin :
        // lookup.requete = ['nin' => '{identifiant}'].
        $requete = [];
        foreach ((array) Arr::get($this->config, 'lookup.requete', []) as $nom => $modele) {
            $requete[$nom] = str_replace('{identifiant}', $recherche->identifiant, (string) $modele);
        }

        $reponse = $this->appeler($chemin, $requete);
        $latence = $this->latence($debut);

        if ($reponse instanceof ResultatRegistre) {
            return $reponse;
        }
        if ($reponse->status() === 404) {
            return ResultatRegistre::introuvable($latence);
        }
        if (! $reponse->successful()) {
            return ResultatRegistre::indisponible('http_'.$reponse->status(), $latence);
        }

        $json = $reponse->json();
        if (! is_array($json)) {
            return ResultatRegistre::indisponible('reponse_illisible', $latence);
        }

        // Réponse sous forme de liste (lookup.liste = chemin de la liste) : vide = introuvable,
        // plusieurs éléments = ambigu, un seul = la fiche.
        $chemin = Arr::get($this->config, 'lookup.liste');
        if (is_string($chemin) && $chemin !== '') {
            $liste = Arr::get($json, $chemin, []);
            if (! is_array($liste) || count($liste) === 0) {
                return ResultatRegistre::introuvable($latence);
            }
            if (count($liste) > 1) {
                return ResultatRegistre::ambigu($latence);
            }
            $json = (array) array_values($liste)[0];
        }

        return ResultatRegistre::trouve($this->extraire($json), $latence);
    }

    public function sante(): array
    {
        $debut = hrtime(true);
        $chemin = (string) Arr::get($this->config, 'sante.chemin', '/sante');
        $reponse = $this->appeler($chemin, []);
        $latence = $this->latence($debut);

        if ($reponse instanceof ResultatRegistre || ! $reponse->successful()) {
            return ['etat' => 'panne', 'latence_ms' => $latence];
        }

        $seuil = (int) Arr::get($this->config, 'sante.seuil_lent_ms', 1000);

        return ['etat' => $latence > $seuil ? 'lent' : 'en_service', 'latence_ms' => $latence];
    }

    /**
     * Recherche par attributs : un seul résultat ou rien. Si plusieurs fiches
     * correspondent, on refuse de deviner (« ambigu »).
     */
    private function parAttributs(Recherche $recherche, int $debut): ResultatRegistre
    {
        $parametres = (array) Arr::get($this->config, 'lookup.recherche.parametres', []);
        $requete = [];
        foreach ($parametres as $interne => $externe) {
            $valeur = $recherche->attributs[$interne] ?? null;
            if ($valeur !== null && $valeur !== '') {
                $requete[$externe] = $interne === 'date_naissance'
                    ? $valeur
                    : Normalisation::texte((string) $valeur);
            }
        }

        if ($requete === []) {
            return ResultatRegistre::introuvable();
        }

        $chemin = (string) Arr::get($this->config, 'lookup.recherche.chemin', '/recherche');
        $reponse = $this->appeler($chemin, $requete);
        $latence = $this->latence($debut);

        if ($reponse instanceof ResultatRegistre) {
            return $reponse;
        }
        if (! $reponse->successful()) {
            return ResultatRegistre::indisponible('http_'.$reponse->status(), $latence);
        }

        $liste = Arr::get((array) $reponse->json(), (string) Arr::get($this->config, 'lookup.recherche.liste', 'resultats'), []);
        if (! is_array($liste) || count($liste) === 0) {
            return ResultatRegistre::introuvable($latence);
        }
        if (count($liste) > 1) {
            return ResultatRegistre::ambigu($latence);
        }

        $donnees = $this->extraire((array) $liste[0]);

        // On ne fait pas aveuglément confiance au registre : la fiche renvoyée doit
        // bien correspondre à ce qu'on cherchait.
        foreach (['nom', 'date_naissance'] as $cle) {
            if (isset($recherche->attributs[$cle], $donnees[$cle])) {
                $concorde = $cle === 'date_naissance'
                    ? Normalisation::memeDate($recherche->attributs[$cle], (string) $donnees[$cle])
                    : Normalisation::texte($recherche->attributs[$cle]) === Normalisation::texte((string) $donnees[$cle]);
                if (! $concorde) {
                    return ResultatRegistre::introuvable($latence);
                }
            }
        }

        return ResultatRegistre::trouve($donnees, $latence);
    }

    /**
     * @param  array<string, mixed>  $requete
     */
    private function appeler(string $chemin, array $requete): Response|ResultatRegistre
    {
        $url = rtrim((string) $this->config['base_url'], '/').'/'.ltrim($chemin, '/');
        $tentatives = max(1, (int) Arr::get($this->config, 'tentatives', 1));
        $timeout = max(1, (int) Arr::get($this->config, 'timeout_ms', 3000)) / 1000;
        $dernier = 'injoignable';

        for ($i = 0; $i < $tentatives; $i++) {
            try {
                $reponse = Http::withHeaders($this->entetes())
                    ->acceptJson()
                    ->timeout($timeout)
                    ->get($url, $requete);

                if ($reponse->serverError()) {
                    $dernier = 'http_'.$reponse->status();

                    continue;
                }

                return $reponse;
            } catch (ConnectionException) {
                $dernier = 'injoignable_ou_delai_depasse';
            } catch (Throwable) {
                $dernier = 'erreur_inattendue';
            }
        }

        return ResultatRegistre::indisponible($dernier);
    }

    /**
     * @return array<string, string>
     */
    private function entetes(): array
    {
        $auth = (array) Arr::get($this->config, 'auth', []);

        return match ($auth['type'] ?? null) {
            'api_key' => [(string) $auth['header'] => (string) $auth['valeur']],
            'bearer' => ['Authorization' => 'Bearer '.$auth['valeur']],
            default => [],
        };
    }

    /**
     * @param  array<mixed>  $json
     * @return array<string, mixed>
     */
    private function extraire(array $json): array
    {
        $donnees = [];
        foreach ((array) ($this->config['champs'] ?? []) as $interne => $chemin) {
            if (Arr::has($json, $chemin)) {
                $donnees[$interne] = Arr::get($json, $chemin);
            }
        }

        // Traduction des valeurs propres à la source vers celles de la plateforme,
        // par exemple « ALIVE » devient « vivant » : 'valeurs' => ['statut' => ['ALIVE' => 'vivant']].
        foreach ((array) ($this->config['valeurs'] ?? []) as $interne => $table) {
            if (isset($donnees[$interne]) && is_scalar($donnees[$interne]) && isset($table[(string) $donnees[$interne]])) {
                $donnees[$interne] = $table[(string) $donnees[$interne]];
            }
        }

        return $donnees;
    }

    private function latence(int $debut): int
    {
        return (int) round((hrtime(true) - $debut) / 1_000_000);
    }

    public function nom(): string
    {
        return $this->nom;
    }
}
