<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
         * Einmalige Zeile mit den bisher hart codierten Werten aus
         * resources/views/emails/partials/signature.blade.php & Co. —
         * ändert sich also am Erscheinungsbild vorerst nichts, bis ein
         * Admin die Einstellungen-Seite tatsächlich bearbeitet.
         */
        DB::table('tb_settings')->insert([
            'name' => 'Schützengilde Angerberg',
            'street' => 'Linden 3',
            'zip' => '6320',
            'city' => 'Angerberg',
            'email' => 'vorstand@sg-angerberg.at',
            'phone' => null,
            'website' => 'www.sg-angerberg.at',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_settings');
    }
};
