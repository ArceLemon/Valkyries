<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Numéro de contact du citoyen, chiffré (cast « encrypted » du modèle).
            $table->text('telephone')->nullable();
        });

        // Messages envoyés au citoyen. Le canal « simule » les range ici au lieu
        // de les confier à un opérateur : c'est ce qui permet de les montrer en démonstration.
        Schema::create('sms_envoyes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verification_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('canal', 20);
            $table->text('destinataire');
            $table->text('message');
            $table->string('statut', 20)->default('envoye');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_envoyes');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('telephone');
        });
    }
};
