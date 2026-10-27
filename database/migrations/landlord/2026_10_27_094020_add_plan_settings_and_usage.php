<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Im Plattform-Admin angepasste Pakete; ohne Zeile gilt der Standard.
        Schema::create('plan_settings', function (Blueprint $table) {
            $table->string('plan', 30)->primary();
            $table->json('features');
            // null = unbegrenzt
            $table->unsignedInteger('members')->nullable();
            $table->unsignedInteger('staff')->nullable();
            $table->unsignedInteger('emails_per_month')->nullable();
            $table->unsignedInteger('storage_mb')->nullable();
            $table->timestamps();
        });

        // Individuelle Abweichungen vom Paket je Verein.
        Schema::table('tenants', function (Blueprint $table) {
            $table->json('feature_overrides')->nullable();
            $table->json('limit_overrides')->nullable();
        });

        // Versendete E-Mails je Verein und Monat (für das Monatslimit).
        Schema::create('tenant_mail_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->char('month', 7);
            $table->unsignedInteger('sent')->default(0);
            $table->unique(['tenant_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_mail_usage');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['feature_overrides', 'limit_overrides']);
        });

        Schema::dropIfExists('plan_settings');
    }
};
