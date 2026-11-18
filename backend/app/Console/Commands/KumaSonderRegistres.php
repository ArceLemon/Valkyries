<?php

namespace App\Console\Commands;

use App\Kuma\Registres\GestionnaireRegistres;
use Illuminate\Console\Command;

class KumaSonderRegistres extends Command
{
    protected $signature = 'kuma:sonder-registres';

    protected $description = 'Vérifie la santé de chaque registre et met à jour l\'état affiché';

    public function handle(GestionnaireRegistres $registres): int
    {
        foreach ($registres->registres() as $nom) {
            $sante = $registres->sonder($nom);
            $this->line(sprintf('%-12s %-11s %s ms', $nom, $sante->etat, $sante->latence_ms ?? '-'));
        }

        return self::SUCCESS;
    }
}
