<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spalten der Mitgliederübersicht: je Staff-Benutzer eine geordnete Liste
     * der sichtbaren Spalten (siehe App\Services\MemberColumns), dazu ein
     * Vereinsstandard in tb_settings.
     */
    public function up(): void
    {
        Schema::create('tb_user_member_columns', function (Blueprint $table) {
            $table->id('userMemberColumnsID');

            // Kein FK: tb_user.id ist ein signed int (Legacy-Tabelle).
            $table->integer('userID')->unique();

            $table->json('columns');
            $table->timestamps();
        });

        Schema::table('tb_settings', function (Blueprint $table) {
            $table->json('member_columns')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->dropColumn('member_columns');
        });

        Schema::dropIfExists('tb_user_member_columns');
    }
};
