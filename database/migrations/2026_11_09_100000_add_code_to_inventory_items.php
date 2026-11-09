<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Barcode oder NFC-Seriennummer für die Schnellausgabe per Scan.
        Schema::table('tb_inventory_items', function (Blueprint $table) {
            $table->string('code', 100)->nullable()->unique()->after('inventory_number');
        });
    }

    public function down(): void
    {
        Schema::table('tb_inventory_items', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
