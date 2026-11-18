<?php

/*
|--------------------------------------------------------------------------
| Registres sources
|--------------------------------------------------------------------------
| Chaque registre est décrit ici. Brancher un nouveau registre, ou celui du
| jury, consiste à changer l'adresse, la clé et la correspondance des champs :
| le moteur ne change pas.
|
|  - type      : "http_json" (API web JSON). D'autres types s'ajoutent en
|                écrivant une classe qui implémente ConnecteurRegistre.
|  - mode      : "calcule_par_kuma_bana" (le registre renvoie la fiche, KUMA BANA
|                calcule la réponse puis détruit la donnée).
|  - lookup    : "identifiant" (recherche par identifiant unique) ou
|                "attributs" (recherche par nom, date et lieu de naissance, avec
|                refus de deviner : un seul résultat ou rien).
|  - champs    : correspondance "champ interne" => "chemin dans la réponse".
*/

$registres = [

    'etat_civil' => [
        'type' => 'http_json',
        'mode' => 'calcule_par_kuma_bana',
        'base_url' => env('REGISTRE_ETAT_CIVIL_URL', 'http://127.0.0.1:8081/etat-civil'),
        'auth' => [
            'type' => 'api_key',
            'header' => 'X-Api-Key',
            'valeur' => env('REGISTRE_ETAT_CIVIL_CLE', 'demo-etat-civil'),
        ],
        'timeout_ms' => (int) env('REGISTRE_TIMEOUT_MS', 3000),
        'tentatives' => 1,
        'sante' => ['chemin' => '/sante', 'seuil_lent_ms' => 1000],
        'lookup' => [
            'mode' => env('REGISTRE_ETAT_CIVIL_LOOKUP', 'identifiant'),
            'chemin' => '/citoyens/{identifiant}',
            'recherche' => [
                'chemin' => '/recherche',
                'parametres' => [
                    'nom' => 'nom',
                    'prenoms' => 'prenoms',
                    'date_naissance' => 'date_naissance',
                    'lieu_naissance' => 'lieu_naissance',
                ],
                'liste' => 'resultats',
            ],
        ],
        'champs' => [
            'nom' => 'identite.nom',
            'prenoms' => 'identite.prenoms',
            'date_naissance' => 'naissance.date',
            'lieu_naissance' => 'naissance.lieu',
            'statut' => 'statut',
            // Facultatif : s'il est fourni, l'inscription du citoyen exige ce numéro.
            'telephone' => 'contact.telephone',
        ],
    ],

    'nationalite' => [
        'type' => 'http_json',
        'mode' => 'calcule_par_kuma_bana',
        'base_url' => env('REGISTRE_NATIONALITE_URL', 'http://127.0.0.1:8081/nationalite'),
        'auth' => [
            'type' => 'api_key',
            'header' => 'X-Api-Key',
            'valeur' => env('REGISTRE_NATIONALITE_CLE', 'demo-nationalite'),
        ],
        'timeout_ms' => (int) env('REGISTRE_TIMEOUT_MS', 3000),
        'tentatives' => 1,
        'sante' => ['chemin' => '/sante', 'seuil_lent_ms' => 1000],
        'lookup' => [
            'mode' => 'identifiant',
            'chemin' => '/citoyens/{identifiant}',
        ],
        'champs' => [
            'nationalite' => 'nationalite',
            'certificat_expire' => 'certificat.valide_jusqu',
        ],
    ],

    'casier' => [
        'type' => 'http_json',
        'mode' => 'calcule_par_kuma_bana',
        'base_url' => env('REGISTRE_CASIER_URL', 'http://127.0.0.1:8081/casier'),
        'auth' => [
            'type' => 'api_key',
            'header' => 'X-Api-Key',
            'valeur' => env('REGISTRE_CASIER_CLE', 'demo-casier'),
        ],
        'timeout_ms' => (int) env('REGISTRE_TIMEOUT_MS', 3000),
        'tentatives' => 1,
        'sante' => ['chemin' => '/sante', 'seuil_lent_ms' => 1000],
        'lookup' => [
            'mode' => 'identifiant',
            'chemin' => '/citoyens/{identifiant}',
        ],
        'champs' => [
            'condamnations' => 'condamnations',
        ],
    ],
];

/*
|--------------------------------------------------------------------------
| Profils : brancher les registres d'une autre équipe sans toucher au code
|--------------------------------------------------------------------------
| REGISTRES_PROFIL=autre-equipe charge profils-registres/autre-equipe.php,
| dont chaque entrée remplace le registre du même nom. Le moteur ne change pas.
*/
$profil = env('REGISTRES_PROFIL');
if (is_string($profil) && $profil !== '' && preg_match('/^[a-z0-9-]+$/', $profil) === 1) {
    $fichier = base_path('profils-registres/'.$profil.'.php');
    if (is_file($fichier)) {
        $registres = array_merge($registres, (array) require $fichier);
    }
}

return $registres;
