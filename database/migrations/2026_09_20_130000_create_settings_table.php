<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_settings', function (Blueprint $table) {
            $table->bigIncrements('settingsID');
            $table->string('name', 150);
            $table->string('street', 150)->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('website', 150)->nullable();
            $table->timestamps();
        });

        /*
         * Die Vereinsdaten der bestehenden Installation wurden hier einmalig
         * eingefügt (Migration ist dort bereits gelaufen). Neue Installationen
         * starten ohne Zeile — Setting::current() legt sie beim ersten Zugriff
         * an, die Daten pflegt ein Admin unter „Einstellungen“.
         */
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_settings');
    }
};
