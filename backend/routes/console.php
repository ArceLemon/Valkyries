<?php

use Illuminate\Support\Facades\Schedule;

// Sonde de santé des registres, puis reprise des demandes mises en attente.
// En développement : php artisan schedule:work
Schedule::command('kuma:sonder-registres')->everyTenSeconds()->withoutOverlapping();
Schedule::command('kuma:reprendre-demandes')->everyTenSeconds()->withoutOverlapping();

// Signature périodique de l'empreinte de tête du journal.
Schedule::command('kuma:point-de-controle')->everyMinute()->withoutOverlapping();
