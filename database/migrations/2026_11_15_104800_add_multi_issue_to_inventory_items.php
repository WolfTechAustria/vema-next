<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mehrfachausgabe: Material kann gleichzeitig an mehrere Mitglieder ausgegeben sein.
        Schema::table('tb_inventory_items', function (Blueprint $table) {
            $table->boolean('multi_issue')->default(false)->after('condition');
        });
    }

    public function down(): void
    {
        Schema::table('tb_inventory_items', function (Blueprint $table) {
            $table->dropColumn('multi_issue');
        });
    }
};
