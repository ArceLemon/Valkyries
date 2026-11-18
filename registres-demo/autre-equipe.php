<?php

declare(strict_types=1);


require __DIR__.'/autre-equipe-lib.php';

$chemin = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$entetes = [];
foreach ($_SERVER as $cle => $valeur) {
    if (str_starts_with($cle, 'HTTP_')) {
        $entetes[strtolower(str_replace('_', '-', substr($cle, 5)))] = (string) $valeur;
    }
}

[$code, $corps] = RegistresAutreEquipe::repondre($_SERVER['REQUEST_METHOD'] ?? 'GET', $chemin, $_GET, $entetes, __DIR__.'/citoyens.json');

http_response_code($code);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
