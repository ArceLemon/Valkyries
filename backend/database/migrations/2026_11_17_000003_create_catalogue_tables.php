<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catalogue des questions (assertions) auxquelles le système sait répondre.
        Schema::create('assertions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('libelle');
            $table->string('registre');
            // Expression de la liste fermée de fonctions, ex. age_gte(date_naissance, 18)
            $table->string('regle');
            $table->string('sensibilite')->default('normale');
            // base_legale ou consentement
            $table->string('regime')->default('base_legale');
            $table->unsignedInteger('validite_minutes')->default(1440);
            $table->boolean('parametree')->default(false);
            $table->boolean('publie')->default(true);
            $table->timestamps();
        });

        // Droits : un organisme peut poser une question pour une finalité donnée.
        Schema::create('access_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assertion_id')->constrained()->cascadeOnDelete();
            $table->string('finalite');
            // La source garde la main : le droit n'est effectif qu'une fois validé.
            $table->boolean('valide_par_source')->default(false);
            $table->timestamp('valide_le')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'assertion_id', 'finalite']);
        });

        // Démarches : une liste de questions et une finalité.
        Schema::create('procedures', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('libelle');
            $table->string('finalite');
            $table->timestamps();
        });

        Schema::create('procedure_assertions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assertion_id')->constrained()->cascadeOnDelete();
            $table->string('parametre')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_assertions');
        Schema::dropIfExists('procedures');
        Schema::dropIfExists('access_rules');
        Schema::dropIfExists('assertions');
    }
};
