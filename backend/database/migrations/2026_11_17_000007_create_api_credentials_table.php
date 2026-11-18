<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Compte technique : créé pour une clé d'API, ne se connecte jamais au portail.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('technique')->default(false);
        });

        Schema::create('api_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('nom');
            // Identifiant public de la clé ; le secret n'est jamais stocké, seulement son empreinte
            $table->string('kid', 12)->unique();
            $table->string('secret_hash', 64);
            // Clé publique Ed25519 de l'organisme : si elle est fournie, chaque demande doit être signée
            $table->text('cle_publique')->nullable();
            // Questions autorisées pour cette clé (null : toutes celles dont l'organisme a le droit)
            $table->json('questions')->nullable();
            $table->unsignedInteger('quota_par_minute')->nullable();
            $table->timestamp('expire_le')->nullable();
            $table->timestamp('revoquee_le')->nullable();
            $table->timestamp('dernier_usage_le')->nullable();
            $table->unsignedBigInteger('cree_par');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_credentials');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('technique');
        });
    }
};
