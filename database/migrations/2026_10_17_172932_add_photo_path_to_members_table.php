<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profilbild eines Mitglieds (400×400-JPEG auf der Disk „local“).
     */
    public function up(): void
    {
        Schema::table('tb_members', function (Blueprint $table) {
            $table->string('photo_path', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tb_members', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
