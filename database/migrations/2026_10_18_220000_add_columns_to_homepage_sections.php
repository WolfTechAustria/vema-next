<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Abschnitte in Spalten: ein Abschnitt „Zwei Spalten“ nimmt weitere
     * Abschnitte links bzw. rechts auf (eine Ebene).
     */
    public function up(): void
    {
        Schema::table('tb_homepage_sections', function (Blueprint $table) {
            $table->unsignedBigInteger('parentID')->nullable()->after('area');
            $table->string('column', 10)->nullable()->after('parentID');

            $table->index(['parentID', 'column', 'sort']);

            $table->foreign('parentID')
                ->references('sectionID')
                ->on('tb_homepage_sections')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tb_homepage_sections', function (Blueprint $table) {
            $table->dropForeign(['parentID']);
            $table->dropIndex(['parentID', 'column', 'sort']);
            $table->dropColumn(['parentID', 'column']);
        });
    }
};
