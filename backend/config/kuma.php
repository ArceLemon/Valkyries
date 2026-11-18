<?php

/*
|--------------------------------------------------------------------------
| Paramètres du noyau KUMA BANA
|--------------------------------------------------------------------------
| Ces valeurs règlent le comportement du système sans toucher au code.
| Les règles métier (organismes, questions, droits) vivent en base de données
| et se modifient depuis l'administration. Les registres sont décrits dans
| config/registres.php.
*/

return [

    // Nombre maximal de demandes par agent et par minute.
    'quota_demandes_par_minute' => (int) env('KUMA_QUOTA_DEMANDES', 30),

    'connexion' => [
        // Tentatives de connexion autorisées par minute (par email et adresse).
        'tentatives_par_minute' => (int) env('KUMA_CONNEXION_TENTATIVES', 5),
        // Durée de validité d'un jeton d'accès.
        'jeton_validite_heures' => (int) env('KUMA_JETON_HEURES', 8),
    ],

    'consentement' => [
        // Durée pendant laquelle une demande attend la décision du citoyen.
        'validite_heures' => (int) env('KUMA_CONSENTEMENT_HEURES', 48),
    ],

    // Prévenir le citoyen quand un organisme attend son accord.
    // « simule » : le message est rangé en base (démonstration). « aucun » : pas d'envoi.
    'notifications' => [
        'canal' => env('KUMA_NOTIFICATIONS', 'simule'),
        // Code à 6 chiffres envoyé avec le message, pour accepter sans smartphone.
        'code_validite_minutes' => (int) env('KUMA_CODE_MINUTES', 15),
        'code_essais_max' => (int) env('KUMA_CODE_ESSAIS', 3),
    ],

    // Notifications push vers l'application, par un serveur ntfy auto-hébergé.
    // url : adresse INTERNE de ntfy (ex. http://ntfy) ; vide = push désactivé.
    // chemin_public : où le téléphone s'abonne, relatif à l'adresse du portail.
    'push' => [
        'url' => (string) env('KUMA_PUSH_URL', ''),
        'jeton' => (string) env('KUMA_PUSH_JETON', ''),
        'cle' => env('KUMA_PUSH_CLE'),
        'chemin_public' => (string) env('KUMA_PUSH_CHEMIN', '/notifications'),
    ],

    // Reprise automatique après une panne de registre.
    'reprise' => [
        'delais_secondes' => [10, 30, 60, 120, 300],
        'max_tentatives' => (int) env('KUMA_REPRISE_MAX', 12),
    ],

    // Coupe-circuit : après N échecs consécutifs, on cesse d'appeler le
    // registre pendant une pause, pour ne pas le saturer.
    'disjoncteur' => [
        'echecs' => (int) env('KUMA_DISJONCTEUR_ECHECS', 3),
        'pause_secondes' => (int) env('KUMA_DISJONCTEUR_PAUSE', 20),
    ],

    // Clés de signature des attestations (Ed25519, libsodium).
    'cles' => [
        'dossier' => env('KUMA_CLES_DIR', storage_path('app/kuma/cles')),
    ],

    // Clé servant à pseudonymiser les identifiants dans les journaux.
    // À défaut, la clé de l'application est utilisée.
    'pseudonyme_cle' => env('KUMA_PSEUDO_KEY'),
];
