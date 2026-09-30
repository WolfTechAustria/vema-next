<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Straße und PLZ sind im Formular optional, in der Alt-Tabelle aber
     * NOT NULL — ein Mitglied ohne Adresse scheiterte daher an MySQL.
     */
    public function up(): void
    {
        Schema::table('tb_members', function (Blueprint $table) {
            $table->string('street', 100)->nullable()->change();
            $table->integer('zip')->nullable()->change();
        });
    }

    /**
     * Bewusst leer: Nach dem Anlegen von Mitgliedern ohne Adresse ließe sich
     * NOT NULL nicht wiederherstellen, ohne Daten zu verändern.
     */
    public function down(): void
    {
        //
    }
};
