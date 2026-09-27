<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Gebuchtes Paket; nach der Testphase ohne Buchung: Starter.
            $table->string('plan', 30)->default('starter');
            // Bei der Registrierung gewähltes Paket (Interesse, nicht gebucht).
            $table->string('requested_plan', 30)->nullable();
            $table->dateTime('trial_ends_at')->nullable();

            $table->string('verification_token', 64)->nullable()->unique();
            $table->dateTime('email_verified_at')->nullable();
            $table->dateTime('provisioned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['verification_token']);
            $table->dropColumn([
                'plan',
                'requested_plan',
                'trial_ends_at',
                'verification_token',
                'email_verified_at',
                'provisioned_at',
            ]);
        });
    }
};
