<?php

namespace App\Kuma\Registres;

/**
 * Ce qu'on sait du citoyen pour retrouver sa fiche dans un registre.
 */
final class Recherche
{
    /**
     * @param  array<string, string>  $attributs  nom, prenoms, date_naissance, lieu_naissance
     */
    public function __construct(
        public readonly ?string $identifiant = null,
        public readonly array $attributs = [],
    ) {}
}
