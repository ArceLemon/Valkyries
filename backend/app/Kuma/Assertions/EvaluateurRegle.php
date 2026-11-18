<?php

namespace App\Kuma\Assertions;

use App\Kuma\Support\Normalisation;
use Carbon\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * Évalue une règle du catalogue sur une donnée brute.
 *
 * Les règles s'écrivent avec une liste FERMÉE de fonctions. Aucun code libre n'est
 * évalué : une configuration ne peut donc pas injecter de code.
 *
 *   age_gte(champ, n)          l'âge calculé depuis une date est au moins n
 *   equals(champ, "valeur")    égalité exacte après normalisation
 *   is_empty(champ)            la liste ou la valeur est vide
 *   in_list(champ, "a|b|c")    la valeur figure dans la liste
 *   date_gte(champ, "today")   la date est aujourd'hui ou plus tard
 *
 * Le paramètre d'une question s'écrit {parametre} dans la règle.
 * Résultat : true (oui), false (non), null (impossible : donnée absente ou illisible).
 */
final class EvaluateurRegle
{
    public const FONCTIONS = ['age_gte', 'equals', 'is_empty', 'in_list', 'date_gte'];

    /**
     * @param  array<string, mixed>  $donnees
     */
    public static function evaluer(string $regle, array $donnees, ?string $parametre = null): ?bool
    {
        [$fonction, $arguments] = self::analyser($regle, $parametre);

        $champ = $arguments[0] ?? '';
        if (! array_key_exists($champ, $donnees)) {
            return null;
        }
        $valeur = $donnees[$champ];

        try {
            return match ($fonction) {
                'age_gte' => self::ageAuMoins($valeur, $arguments[1] ?? null),
                'equals' => self::egal($valeur, $arguments[1] ?? null),
                'is_empty' => empty($valeur),
                'in_list' => self::dansListe($valeur, $arguments[1] ?? null),
                'date_gte' => self::dateAuPlusTot($valeur, $arguments[1] ?? null),
            };
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Vérifie qu'une règle est bien formée (fonction connue, bon nombre d'arguments).
     */
    public static function valider(string $regle): bool
    {
        try {
            [$fonction, $arguments] = self::analyser($regle, null);

            return $fonction === 'is_empty' ? count($arguments) === 1 : count($arguments) === 2;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private static function analyser(string $regle, ?string $parametre): array
    {
        if (! preg_match('/^\s*([a-z_]+)\((.*)\)\s*$/s', $regle, $m)) {
            throw new InvalidArgumentException("Règle mal formée : {$regle}");
        }

        $fonction = $m[1];
        if (! in_array($fonction, self::FONCTIONS, true)) {
            throw new InvalidArgumentException("Fonction inconnue : {$fonction}");
        }

        $arguments = array_map('trim', str_getcsv($m[2], ',', '"', ''));
        $arguments = array_map(
            fn (string $a) => $a === '{parametre}' ? (string) $parametre : $a,
            $arguments,
        );

        return [$fonction, $arguments];
    }

    private static function ageAuMoins(mixed $valeur, ?string $minimum): bool
    {
        return Carbon::parse((string) $valeur)->age >= (int) $minimum;
    }

    private static function egal(mixed $valeur, ?string $attendu): bool
    {
        return Normalisation::texte((string) $valeur) === Normalisation::texte((string) $attendu);
    }

    private static function dansListe(mixed $valeur, ?string $liste): bool
    {
        $options = array_map(
            fn (string $o) => Normalisation::texte($o),
            explode('|', (string) $liste),
        );

        return in_array(Normalisation::texte((string) $valeur), $options, true);
    }

    private static function dateAuPlusTot(mixed $valeur, ?string $reference): bool
    {
        $limite = ($reference === null || $reference === 'today')
            ? Carbon::today()
            : Carbon::parse($reference)->startOfDay();

        return Carbon::parse((string) $valeur)->startOfDay()->gte($limite);
    }
}
