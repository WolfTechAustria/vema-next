<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Tabelle existiert bereits.
        // Diese Migration bleibt absichtlich ohne Aktion.
    }

    public function down(): void
    {
        // Absichtlich leer.
        // Die bestehende Tabelle darf nicht gelöscht werden.
    }
};
