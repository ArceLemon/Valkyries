<?php

namespace App\Kuma\Registres;

/**
 * Contrat unique entre le moteur et un registre. Le moteur ne connaît que ceci :
 * « donne-moi la fiche de ce citoyen » et « es-tu en service ? ».
 *
 * Brancher un registre dont le format est inattendu revient à écrire une classe
 * qui implémente cette interface, sans toucher au moteur.
 */
interface ConnecteurRegistre
{
    public function rechercher(Recherche $recherche): ResultatRegistre;

    /**
     * @return array{etat: string, latence_ms: int|null}  etat : en_service, lent ou panne
     */
    public function sante(): array;
}
