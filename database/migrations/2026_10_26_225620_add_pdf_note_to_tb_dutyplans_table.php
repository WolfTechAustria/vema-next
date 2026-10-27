<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_dutyplans', function (Blueprint $table) {
            $table->string('pdf_note')->nullable()->after('exclude_holidays');
        });
    }

    public function down(): void
    {
        Schema::table('tb_dutyplans', function (Blueprint $table) {
            $table->dropColumn('pdf_note');
        });
    }
};
