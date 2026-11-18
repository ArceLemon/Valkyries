<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('sigle')->unique();
            $table->string('tutelle')->nullable();
            // "demandeuse", "fournisseuse", ou les deux
            $table->json('roles');
            // Registres détenus par l'organisme (clés de config/registres.php)
            $table->json('registres')->nullable();
            // preparation, actif, suspendu
            $table->string('statut')->default('preparation');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
