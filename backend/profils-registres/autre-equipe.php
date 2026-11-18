<?php

/*
|--------------------------------------------------------------------------
| Profil « autre équipe » : trois registres au format différent du nôtre
|--------------------------------------------------------------------------
| Activation : REGISTRES_PROFIL=autre-equipe
| Adresse    : REGISTRES_AUTRE_URL   (défaut http://127.0.0.1:8082/v2)
| Jeton      : REGISTRES_AUTRE_JETON (défaut tok-autre-equipe)
|
| C'est le modèle à copier pour brancher n'importe quel registre : on décrit
| l'adresse, l'authentification, comment retrouver une personne, et la
| correspondance des champs. Aucune ligne du moteur n'est modifiée.
*/

$base = (string) env('REGISTRES_AUTRE_URL', 'http://127.0.0.1:8082/v2');
$auth = ['type' => 'bearer', 'valeur' => (string) env('REGISTRES_AUTRE_JETON', 'tok-autre-equipe')];
$commun = [
    'type' => 'http_json',
    'mode' => 'calcule_par_kuma_bana',
    'base_url' => $base,
    'auth' => $auth,
    'timeout_ms' => (int) env('REGISTRE_TIMEOUT_MS', 3000),
    'tentatives' => 1,
    'sante' => ['chemin' => '/health', 'seuil_lent_ms' => 1000],
];

return [
    'etat_civil' => $commun + [
        'lookup' => [
            'mode' => 'identifiant',
            'chemin' => '/civil/persons',
            'requete' => ['nin' => '{identifiant}'],
            'liste' => 'data',
        ],
        'champs' => [
            'nom' => 'surname',
            'date_naissance' => 'born.on',
            'lieu_naissance' => 'born.at',
            'statut' => 'life_status',
        ],
        'valeurs' => [
            'statut' => ['ALIVE' => 'vivant', 'DECEASED' => 'decede'],
        ],
    ],

    'nationalite' => $commun + [
        'lookup' => [
            'mode' => 'identifiant',
            'chemin' => '/nationality/holders',
            'requete' => ['nin' => '{identifiant}'],
            'liste' => 'data',
        ],
        'champs' => [
            'nationalite' => 'citizenship',
            'certificat_expire' => 'certificate.expires',
        ],
        'valeurs' => [
            'nationalite' => ['BFA' => 'BF', 'CIV' => 'CI', 'MLI' => 'ML', 'NER' => 'NE', 'GHA' => 'GH', 'TGO' => 'TG'],
        ],
    ],

    'casier' => $commun + [
        'lookup' => [
            'mode' => 'identifiant',
            'chemin' => '/judicial/records',
            'requete' => ['nin' => '{identifiant}'],
            'liste' => 'data',
        ],
        'champs' => [
            'condamnations' => 'convictions',
        ],
    ],
];
