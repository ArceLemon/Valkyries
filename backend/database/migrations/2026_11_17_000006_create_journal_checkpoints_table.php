<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Points de contrôle : l'empreinte de tête du journal, signée (Ed25519).
        // Ils empêchent de réécrire toute la chaîne après coup.
        Schema::create('journal_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dernier_id')->index();
            $table->string('hash', 64);
            $table->unsignedInteger('total');
            $table->string('kid');
            $table->text('jeton');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_checkpoints');
    }
};
