<?php

namespace App\Console\Commands;

use App\Kuma\Demandes\ServiceDemandes;
use Illuminate\Console\Command;

class KumaReprendreDemandes extends Command
{
    protected $signature = 'kuma:reprendre-demandes';

    protected $description = 'Relance les demandes en attente d\'un registre et expire les consentements non décidés';

    public function handle(ServiceDemandes $service): int
    {
        $reprises = $service->reprendre();
        $this->info("{$reprises} demande(s) relancée(s).");

        return self::SUCCESS;
    }
}
