<?php

namespace App\Kuma\Support;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Normalisation avant comparaison : majuscules, sans accents, sans ponctuation.
 * La comparaison reste exacte après normalisation, jamais approximative.
 */
final class Normalisation
{
    public static function texte(?string $valeur): string
    {
        $valeur = mb_strtoupper(Str::ascii((string) $valeur));
        $valeur = preg_replace('/[^A-Z0-9]+/', ' ', $valeur) ?? '';

        return trim($valeur);
    }

    /**
     * Les 8 derniers chiffres : « +226 70 00 00 04 », « 0022670000004 » et
     * « 70000004 » désignent le même numéro burkinabè.
     */
    public static function telephone(?string $valeur): string
    {
        $chiffres = preg_replace('/\D+/', '', (string) $valeur) ?? '';

        return strlen($chiffres) > 8 ? substr($chiffres, -8) : $chiffres;
    }

    public static function memeDate(string $a, string $b): bool
    {
        try {
            return Carbon::parse($a)->toDateString() === Carbon::parse($b)->toDateString();
        } catch (Throwable) {
            return false;
        }
    }
}
