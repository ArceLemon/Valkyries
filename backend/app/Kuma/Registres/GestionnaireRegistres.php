<?php

namespace App\Kuma\Registres;

use App\Models\RegistryHealth;
use InvalidArgumentException;

/**
 * Point d'accès unique aux registres. Il construit le bon connecteur à partir de
 * config/registres.php et applique le coupe-circuit.
 *
 * Coupe-circuit : après N échecs consécutifs, on cesse d'appeler le registre
 * pendant une pause (évite de le saturer). Quand la pause est écoulée, le prochain
 * appel sert d'essai : un succès referme le circuit, un échec le rouvre.
 */
final class GestionnaireRegistres
{
    public function connecteur(string $registre): ConnecteurRegistre
    {
        $config = config("registres.{$registre}");
        if (! is_array($config)) {
            throw new InvalidArgumentException("Registre inconnu : {$registre}");
        }

        return match ($config['type'] ?? null) {
            'http_json' => new HttpJsonConnecteur($registre, $config),
            default => throw new InvalidArgumentException("Type de connecteur non pris en charge : {$registre}"),
        };
    }

    /**
     * @return list<string>
     */
    public function registres(): array
    {
        return array_keys((array) config('registres', []));
    }

    public function interroger(string $registre, Recherche $recherche): ResultatRegistre
    {
        $sante = $this->sante($registre);

        if ($sante->disjoncteurOuvert()) {
            return ResultatRegistre::indisponible('disjoncteur_ouvert');
        }

        $resultat = $this->connecteur($registre)->rechercher($recherche);

        if ($resultat->estIndisponible()) {
            $sante->echecs_consecutifs++;
            $sante->etat = 'panne';
            if ($sante->echecs_consecutifs >= (int) config('kuma.disjoncteur.echecs', 3)) {
                $sante->ouvert_jusqu_au = now()->addSeconds((int) config('kuma.disjoncteur.pause_secondes', 20));
            }
        } else {
            $sante->echecs_consecutifs = 0;
            $sante->ouvert_jusqu_au = null;
            $sante->etat = 'en_service';
            $sante->latence_ms = $resultat->latenceMs;
        }
        $sante->verifie_le = now();
        $sante->save();

        return $resultat;
    }

    /**
     * Sonde de santé : met à jour l'état affiché et referme le circuit si le
     * registre répond de nouveau.
     */
    public function sonder(string $registre): RegistryHealth
    {
        $mesure = $this->connecteur($registre)->sante();
        $sante = $this->sante($registre);

        $sante->etat = $mesure['etat'];
        $sante->latence_ms = $mesure['latence_ms'];
        $sante->verifie_le = now();

        if ($mesure['etat'] === 'panne') {
            $sante->echecs_consecutifs++;
        } else {
            $sante->echecs_consecutifs = 0;
            $sante->ouvert_jusqu_au = null;
        }
        $sante->save();

        return $sante;
    }

    public function sante(string $registre): RegistryHealth
    {
        return RegistryHealth::firstOrCreate(
            ['registre' => $registre],
            ['etat' => 'en_service', 'echecs_consecutifs' => 0],
        );
    }
}
