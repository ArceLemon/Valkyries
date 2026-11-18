<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assertions', function (Blueprint $table) {
            $table->text('description')->nullable();
            // Texte juridique de référence. À confirmer par l'administration responsable.
            $table->string('base_juridique')->nullable();
        });

        Schema::table('access_rules', function (Blueprint $table) {
            // Pourquoi l'organisme demande ce droit
            $table->text('justification')->nullable();
            // Référence de la convention ou du texte, renseignée par la source à la validation
            $table->string('reference_juridique')->nullable();
            // Un droit peut être limité dans le temps
            $table->timestamp('expire_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('access_rules', function (Blueprint $table) {
            $table->dropColumn(['justification', 'reference_juridique', 'expire_le']);
        });
        Schema::table('assertions', function (Blueprint $table) {
            $table->dropColumn(['description', 'base_juridique']);
        });
    }
};
