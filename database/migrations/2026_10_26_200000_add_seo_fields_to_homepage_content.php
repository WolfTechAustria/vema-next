<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eigener Titel und eigene Beschreibung für Suchmaschinen je Beitrag und
     * Seite; bei übernommenen Beiträgen die Adresse auf der alten Homepage
     * (für Weiterleitungen).
     */
    public function up(): void
    {
        Schema::table('tb_posts', function (Blueprint $table) {
            $table->string('seo_title', 120)->nullable()->after('excerpt');
            $table->string('seo_description', 300)->nullable()->after('seo_title');
            $table->string('legacy_path', 255)->nullable()->after('slug');

            $table->index('legacy_path');
        });

        Schema::table('tb_homepage_pages', function (Blueprint $table) {
            $table->string('seo_title', 120)->nullable()->after('subtitle');
            $table->string('seo_description', 300)->nullable()->after('seo_title');
        });
    }

    public function down(): void
    {
        Schema::table('tb_posts', function (Blueprint $table) {
            $table->dropIndex(['legacy_path']);
            $table->dropColumn(['seo_title', 'seo_description', 'legacy_path']);
        });

        Schema::table('tb_homepage_pages', function (Blueprint $table) {
            $table->dropColumn(['seo_title', 'seo_description']);
        });
    }
};
