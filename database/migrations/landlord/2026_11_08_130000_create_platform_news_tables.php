<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Neuigkeiten zu VEMA (neue Funktionen) für die Admins der Vereine.
        Schema::create('platform_news', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('body');
            // Nur für Vereine ab diesem Paket; null = alle
            $table->string('min_plan', 30)->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('emailed_at')->nullable();
            $table->unsignedInteger('emailed_count')->default(0);
            $table->foreignId('platform_admin_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamps();
        });

        // Bis wann ein Benutzer eines Vereins die Neuigkeiten gesehen hat.
        Schema::create('platform_news_seen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->integer('user_id');
            $table->timestamp('seen_at');
            $table->unique(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_news_seen');
        Schema::dropIfExists('platform_news');
    }
};
