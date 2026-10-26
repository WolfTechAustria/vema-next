<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weiterleitungen alter Adressen (z. B. der früheren Homepage) und ein
     * Protokoll der Adressen, die auf der Homepage nicht gefunden wurden.
     */
    public function up(): void
    {
        Schema::create('tb_homepage_redirects', function (Blueprint $table) {
            $table->id('redirectID');
            $table->string('source_path', 255)->unique();
            $table->string('target', 500);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tb_homepage_not_found', function (Blueprint $table) {
            $table->id('notFoundID');
            $table->string('path', 255)->unique();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_homepage_not_found');
        Schema::dropIfExists('tb_homepage_redirects');
    }
};
