<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vereinseinstellung: Zwei-Faktor-Anmeldung für alle Funktionäre verpflichtend.
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->boolean('two_factor_required')->default(false);
        });

        Schema::table('tb_user', function (Blueprint $table) {
            // „app“ (Authenticator-App) oder „email“ (Code per E-Mail).
            $table->string('two_factor_method', 10)->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tb_user', function (Blueprint $table) {
            $table->dropColumn(['two_factor_method', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });

        Schema::table('tb_settings', function (Blueprint $table) {
            $table->dropColumn('two_factor_required');
        });
    }
};
