<?php

namespace App\Console\Commands;

use App\Kuma\Preuves\Signataire;
use Illuminate\Console\Command;

class KumaGenererCles extends Command
{
    protected $signature = 'kuma:generer-cles';

    protected $description = 'Génère une paire de clés Ed25519 pour signer les attestations (rotation : les anciennes clés restent valables pour vérifier)';

    public function handle(Signataire $signataire): int
    {
        $kid = $signataire->generer();
        $this->info("Nouvelle clé de signature active : {$kid}");

        return self::SUCCESS;
    }
}
