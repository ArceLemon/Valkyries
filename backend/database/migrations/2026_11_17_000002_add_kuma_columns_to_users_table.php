<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // citoyen, agent, responsable, gestionnaire, administrateur, auditeur
            $table->string('role')->default('citoyen');
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            // Identifiant du citoyen dans les registres (comptes citoyens)
            $table->string('identifiant')->nullable()->unique();
            $table->boolean('actif')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'organization_id', 'identifiant', 'actif']);
        });
    }
};
