<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->string('list_background_path')->nullable();
            $table->unsignedTinyInteger('list_margin_top')->default(15);
            $table->unsignedTinyInteger('list_margin_right')->default(15);
            $table->unsignedTinyInteger('list_margin_bottom')->default(15);
            $table->unsignedTinyInteger('list_margin_left')->default(15);
        });
    }

    public function down(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->dropColumn([
                'list_background_path',
                'list_margin_top',
                'list_margin_right',
                'list_margin_bottom',
                'list_margin_left',
            ]);
        });
    }
};
