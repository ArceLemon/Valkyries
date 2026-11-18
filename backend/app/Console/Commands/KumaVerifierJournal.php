<?php

namespace App\Console\Commands;

use App\Kuma\Journal\Journal;
use Illuminate\Console\Command;

class KumaVerifierJournal extends Command
{
    protected $signature = 'kuma:verifier-journal';

    protected $description = 'Recalcule la chaîne d\'empreintes du journal et signale la première rupture';

    public function handle(Journal $journal): int
    {
        $resultat = $journal->verifier();

        if ($resultat['valide']) {
            $this->info("Journal intègre : {$resultat['total']} ligne(s) vérifiée(s).");

            return self::SUCCESS;
        }

        $this->error("Journal altéré à la ligne {$resultat['rupture']['id']} ({$resultat['rupture']['motif']}).");

        return self::FAILURE;
    }
}
