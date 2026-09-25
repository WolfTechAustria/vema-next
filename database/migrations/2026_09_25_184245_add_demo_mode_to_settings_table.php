<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->boolean('demo_enabled')->default(false);
            $table->string('demo_reset_mode', 20)->default('nightly');
            $table->dateTime('demo_last_reset_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->dropColumn([
                'demo_enabled',
                'demo_reset_mode',
                'demo_last_reset_at',
            ]);
        });
    }
};
