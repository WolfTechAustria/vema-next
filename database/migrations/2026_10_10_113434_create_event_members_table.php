<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eingeschränkte Sichtbarkeit eigener Termine: Sind Mitglieder
     * zugeordnet, sehen nur diese den Termin — ohne Zuordnung alle.
     */
    public function up(): void
    {
        Schema::create('tb_event_members', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('eventID');
            $table->integer('memberID');

            $table->timestamps();

            $table->unique([
                'eventID',
                'memberID',
            ]);

            $table->index('memberID');

            $table->foreign('eventID')
                ->references('eventID')
                ->on('tb_events')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_event_members');
    }
};
