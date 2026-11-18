<?php

namespace App\Kuma\Support;

/**
 * Remplace un identifiant par une empreinte HMAC-SHA256 : on peut retrouver les
 * lignes d'un même citoyen sans jamais écrire son identifiant dans le journal.
 */
final class Pseudonymiseur
{
    public function pour(string $valeur): string
    {
        $cle = (string) (config('kuma.pseudonyme_cle') ?: config('app.key'));

        return hash_hmac('sha256', Normalisation::texte($valeur), $cle);
    }
}
