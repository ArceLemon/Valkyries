<?php

declare(strict_types=1);

/**
 * Serveur des registres de démonstration (développement local uniquement).
 *
 *   php -S 127.0.0.1:8081 registres-demo/router.php
 *
 * Simuler une panne devant le jury, depuis un navigateur ou curl :
 *   http://127.0.0.1:8081/admin/panne?registre=etat-civil&mode=down   (arrêt)
 *   http://127.0.0.1:8081/admin/panne?registre=etat-civil&mode=lent   (lent, 2 s)
 *   http://127.0.0.1:8081/admin/panne?registre=etat-civil&mode=ok     (rétabli)
 *   http://127.0.0.1:8081/admin/etat                                  (état courant)
 *
 * Citoyens fictifs : voir citoyens.json. Aucune donnée réelle.
 */

require __DIR__.'/lib.php';

header('Content-Type: application/json; charset=utf-8');

$chemin = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$fichierEtat = __DIR__.'/etat.json';
$pannes = is_file($fichierEtat) ? (json_decode((string) file_get_contents($fichierEtat), true) ?: []) : [];

if ($chemin === '/admin/etat') {
    echo json_encode($pannes === [] ? new stdClass : $pannes);

    return;
}

if ($chemin === '/admin/panne') {
    $registre = $_GET['registre'] ?? '';
    $mode = $_GET['mode'] ?? '';
    if (! in_array($registre, RegistresDemo::REGISTRES, true) || ! in_array($mode, ['ok', 'down', 'lent'], true)) {
        http_response_code(422);
        echo json_encode(['erreur' => 'parametres_invalides', 'registres' => RegistresDemo::REGISTRES, 'modes' => ['ok', 'down', 'lent']]);

        return;
    }
    $pannes[$registre] = $mode;
    file_put_contents($fichierEtat, json_encode($pannes, JSON_PRETTY_PRINT));
    echo json_encode(['registre' => $registre, 'mode' => $mode]);

    return;
}

$entetes = [];
foreach ($_SERVER as $cle => $valeur) {
    if (str_starts_with($cle, 'HTTP_')) {
        $entetes[strtolower(str_replace('_', '-', substr($cle, 5)))] = (string) $valeur;
    }
}

[$code, $corps] = RegistresDemo::repondre($methode, $chemin, $_GET, $entetes, $pannes, __DIR__.'/citoyens.json');
http_response_code($code);
echo json_encode($corps, JSON_UNESCAPED_UNICODE);
