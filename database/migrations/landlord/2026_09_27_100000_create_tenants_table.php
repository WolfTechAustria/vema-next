<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zentrale Datenbank (TENANCY_MODE=multi). Wird nur über
 * "php artisan migrate --database=landlord --path=database/migrations/landlord"
 * ausgeführt — die normale Migrationskette liest keine Unterordner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 63)->unique();
            $table->string('name', 150);
            $table->string('database', 64)->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->date('license_valid_until')->nullable();
            $table->string('contact_name', 150)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('domain', 190)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }
};
