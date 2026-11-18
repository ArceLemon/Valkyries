<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Jeu de démonstration : organismes, comptes, catalogue, droits et démarches,
     * tous FICTIFS.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
