<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bereits als Entwurf übernommene Neuigkeiten aus resources/news —
        // bleibt auch erhalten, wenn der Entwurf gelöscht wird.
        Schema::create('platform_news_sources', function (Blueprint $table) {
            $table->id();
            $table->string('key', 150)->unique();
            $table->foreignId('platform_news_id')->nullable()->constrained('platform_news')->nullOnDelete();
            $table->timestamp('imported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_news_sources');
    }
};
