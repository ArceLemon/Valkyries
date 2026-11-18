<?php

namespace App\Console\Commands;

use App\Kuma\Journal\Journal;
use Illuminate\Console\Command;

class KumaPointDeControle extends Command
{
    protected $signature = 'kuma:point-de-controle';

    protected $description = 'Signe l\'empreinte de tête du journal (point de contrôle), pour empêcher toute réécriture de l\'historique';

    public function handle(Journal $journal): int
    {
        $point = $journal->creerPointDeControle();

        if ($point === null) {
            $this->line('Aucun changement depuis le dernier point de contrôle.');

            return self::SUCCESS;
        }

        $this->info("Point de contrôle signé : ligne {$point->dernier_id}, {$point->total} ligne(s).");

        return self::SUCCESS;
    }
}
