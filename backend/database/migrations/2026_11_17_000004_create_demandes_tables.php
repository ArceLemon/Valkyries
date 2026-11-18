<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            // Regroupe les demandes lancées par une même démarche
            $table->string('lot')->nullable()->index();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('assertion_id')->constrained();
            $table->string('parametre')->nullable();
            $table->string('finalite');
            $table->string('statut')->index();
            // oui, non, impossible : la seule information qui sort du registre
            $table->string('reponse')->nullable();
            $table->string('motif')->nullable();
            $table->string('source')->nullable();
            // Identifiant du citoyen pseudonymisé (jamais l'identifiant lui-même)
            $table->string('sujet_pseudo')->index();
            // Éléments nécessaires au traitement, chiffrés, effacés dès la fin
            $table->text('identifiant_enc')->nullable();
            $table->text('date_naissance_enc')->nullable();
            $table->text('recherche_enc')->nullable();
            $table->unsignedInteger('tentatives')->default(0);
            $table->timestamp('prochaine_tentative_at')->nullable();
            $table->timestamp('traite_le')->nullable();
            $table->timestamps();
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_request_id')->constrained()->cascadeOnDelete();
            $table->string('sujet_pseudo')->index();
            // en_attente, accepte, refuse, retire, expire
            $table->string('statut')->default('en_attente');
            $table->timestamp('expire_le');
            $table->timestamp('decide_le')->nullable();
            $table->timestamps();
        });

        Schema::create('attestations', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('verification_request_id')->constrained()->cascadeOnDelete();
            $table->text('jeton');
            $table->string('kid');
            $table->timestamp('valide_jusqu_au');
            $table->timestamp('revoquee_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attestations');
        Schema::dropIfExists('consents');
        Schema::dropIfExists('verification_requests');
    }
};
