<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Journal chaîné : chaque ligne contient l'empreinte de la précédente.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Texte, pour que l'empreinte se recalcule à l'identique
            $table->string('horodatage', 32);
            $table->string('acteur_type');
            $table->string('acteur_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->string('action')->index();
            $table->string('objet_type')->nullable();
            $table->string('objet_ref')->nullable();
            $table->string('sujet_pseudo')->nullable()->index();
            $table->json('details');
            $table->string('resultat')->nullable();
            $table->string('prev_hash', 64);
            $table->string('hash', 64)->unique();
        });

        Schema::create('registry_healths', function (Blueprint $table) {
            $table->id();
            $table->string('registre')->unique();
            // en_service, lent, panne
            $table->string('etat')->default('en_service');
            $table->unsignedInteger('latence_ms')->nullable();
            $table->timestamp('verifie_le')->nullable();
            $table->unsignedInteger('echecs_consecutifs')->default(0);
            $table->timestamp('ouvert_jusqu_au')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registry_healths');
        Schema::dropIfExists('audit_logs');
    }
};
