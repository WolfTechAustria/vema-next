<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Die ursprüngliche Migration (2026_09_12_182934) ist leer, weil die
     * Tabelle auf der ersten Installation schon von Hand angelegt war. Neue
     * Datenbanken (frisch registrierte Vereine, Tests) bekamen sie deshalb
     * nie — Empfängergruppen mit externen Kontakten, Geburtstags-Mails und
     * die Dienstplan-Einteilung schlugen dort fehl.
     *
     * Bestehende Tabellen bleiben unverändert.
     */
    public function up(): void
    {
        if (Schema::hasTable('tb_recipient_group_external_contacts')) {
            return;
        }

        Schema::create('tb_recipient_group_external_contacts', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('groupID');
            $table->unsignedBigInteger('externalContactID');

            $table->timestamps();

            $table->unique([
                'groupID',
                'externalContactID',
            ]);

            $table->index('externalContactID');
        });
    }

    /**
     * Bewusst leer: Auf bestehenden Installationen enthält die Tabelle
     * Vereinsdaten und darf durch ein Rollback nicht gelöscht werden.
     */
    public function down(): void
    {
        //
    }
};
