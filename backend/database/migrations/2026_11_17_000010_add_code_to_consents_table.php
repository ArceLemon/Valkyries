<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Code de confirmation envoyé par SMS, pour le citoyen sans smartphone.
        // On ne garde que son empreinte : le code lui-même n'existe que dans le SMS.
        Schema::table('consents', function (Blueprint $table) {
            $table->string('code_hash')->nullable();
            $table->timestamp('code_expire_le')->nullable();
            $table->unsignedTinyInteger('code_essais')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('consents', function (Blueprint $table) {
            $table->dropColumn(['code_hash', 'code_expire_le', 'code_essais']);
        });
    }
};
