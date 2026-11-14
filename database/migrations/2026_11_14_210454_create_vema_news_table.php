<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Neuigkeiten zu VEMA, von der Plattform (vemat.at) übernommen — nur für eigene Installationen.
        Schema::create('tb_vema_news', function (Blueprint $table) {
            $table->id('newsID');
            $table->unsignedBigInteger('remote_id')->unique();
            $table->string('title', 150);
            $table->text('body');
            $table->string('min_plan', 30)->nullable();
            $table->timestamp('published_at')->index();
            // An die Admins gemailt (oder beim ersten Abruf bewusst übersprungen)
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('tb_user', function (Blueprint $table) {
            $table->timestamp('news_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_vema_news');

        Schema::table('tb_user', function (Blueprint $table) {
            $table->dropColumn('news_seen_at');
        });
    }
};
