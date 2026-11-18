<?php

namespace App\Kuma\Registres;

/**
 * Réponse d'un registre, ramenée à quatre cas. Jamais de devinette :
 * un registre qui ne répond pas n'est ni un « oui » ni un « non ».
 */
final class ResultatRegistre
{
    public const TROUVE = 'trouve';

    public const INTROUVABLE = 'introuvable';

    public const AMBIGU = 'ambigu';

    public const INDISPONIBLE = 'indisponible';

    /**
     * @param  array<string, mixed>  $donnees  champs internes extraits de la fiche
     */
    private function __construct(
        public readonly string $statut,
        public readonly array $donnees = [],
        public readonly ?int $latenceMs = null,
        public readonly ?string $detail = null,
    ) {}

    /**
     * @param  array<string, mixed>  $donnees
     */
    public static function trouve(array $donnees, ?int $latenceMs = null): self
    {
        return new self(self::TROUVE, $donnees, $latenceMs);
    }

    public static function introuvable(?int $latenceMs = null): self
    {
        return new self(self::INTROUVABLE, [], $latenceMs);
    }

    public static function ambigu(?int $latenceMs = null): self
    {
        return new self(self::AMBIGU, [], $latenceMs);
    }

    public static function indisponible(string $detail, ?int $latenceMs = null): self
    {
        return new self(self::INDISPONIBLE, [], $latenceMs, $detail);
    }

    public function estIndisponible(): bool
    {
        return $this->statut === self::INDISPONIBLE;
    }
}
