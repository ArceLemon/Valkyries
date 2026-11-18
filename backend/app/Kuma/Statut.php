<?php

namespace App\Kuma;

/**
 * États d'une demande de vérification.
 */
final class Statut
{
    public const RECUE = 'recue';

    public const EN_ATTENTE_CONSENTEMENT = 'en_attente_consentement';

    public const EN_COURS = 'en_cours';

    public const EN_ATTENTE_REGISTRE = 'en_attente_registre';

    public const TERMINEE = 'terminee';

    public const AMBIGUE = 'ambigue';

    public const REFUSEE_CITOYEN = 'refusee_citoyen';

    public const EXPIREE = 'expiree';

    /** États finaux : la demande n'évoluera plus. */
    public const FINAUX = [self::TERMINEE, self::AMBIGUE, self::REFUSEE_CITOYEN, self::EXPIREE];
}
