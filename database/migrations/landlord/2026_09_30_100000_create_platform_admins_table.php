<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Betreiber der Plattform (Login unter <platform_host>/admin).
        Schema::create('platform_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 150)->unique();
            $table->string('password');
            $table->rememberToken();
            $table->dateTime('last_login_at')->nullable();
            $table->timestamps();
        });

        // Zuletzt verschickte Erinnerung — verhindert doppelte Mails.
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('trial_reminder', 20)->nullable();
            $table->string('license_reminder', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['trial_reminder', 'license_reminder']);
        });

        Schema::dropIfExists('platform_admins');
    }
};
