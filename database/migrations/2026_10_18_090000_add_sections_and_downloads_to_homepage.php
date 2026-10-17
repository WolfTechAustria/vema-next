<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mehr gestaltbare Inhalte der Vereinshomepage: Startseiten-Elemente
     * (Ankündigung, Kacheln, Kennzahlen), Seitenüberschriften, Abschnitte
     * für Seiten und „Über uns“ sowie PDF-Downloads zu Beiträgen.
     */
    public function up(): void
    {
        Schema::table('tb_homepage_settings', function (Blueprint $table) {
            $table->string('hero_eyebrow', 150)->nullable()->after('hero_title');
            $table->string('hero_button_label', 60)->nullable()->after('hero_image_path');
            $table->string('hero_button_url', 255)->nullable()->after('hero_button_label');

            $table->boolean('announcement_enabled')->default(false);
            $table->string('announcement_badge', 40)->nullable();
            $table->string('announcement_title', 200)->nullable();
            $table->text('announcement_text')->nullable();
            $table->string('announcement_image_path', 255)->nullable();
            $table->string('announcement_link_label', 80)->nullable();
            $table->string('announcement_link_url', 255)->nullable();
            $table->string('announcement_file_path', 255)->nullable();

            $table->json('tiles')->nullable();
            $table->json('stats')->nullable();
            $table->json('page_headings')->nullable();

            $table->text('contact_text')->nullable();
            $table->string('footer_info_title', 60)->nullable();
            $table->text('footer_info_text')->nullable();
            $table->boolean('show_member_area_link')->default(false);
        });

        Schema::table('tb_homepage_pages', function (Blueprint $table) {
            $table->string('subtitle', 200)->nullable()->after('title');
        });

        Schema::create('tb_homepage_sections', function (Blueprint $table) {
            $table->bigIncrements('sectionID');

            // Seite (area "page") oder die feste Seite „Über uns“ (area "about").
            $table->unsignedBigInteger('pageID')->nullable();
            $table->string('area', 20)->default('page');

            $table->string('type', 30);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('alt_background')->default(false);

            $table->string('heading', 200)->nullable();
            $table->longText('body_html')->nullable();
            $table->json('data')->nullable();

            $table->string('image_path', 255)->nullable();
            $table->string('image2_path', 255)->nullable();
            $table->string('file_path', 255)->nullable();

            $table->timestamps();

            $table->index(['area', 'pageID', 'sort']);

            $table->foreign('pageID')
                ->references('pageID')
                ->on('tb_homepage_pages')
                ->cascadeOnDelete();
        });

        Schema::create('tb_post_attachments', function (Blueprint $table) {
            $table->bigIncrements('postAttachmentID');

            $table->unsignedBigInteger('postID');
            $table->string('label', 200);
            $table->string('file_path', 255);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('sort')->default(0);

            $table->timestamps();

            $table->index(['postID', 'sort']);

            $table->foreign('postID')
                ->references('postID')
                ->on('tb_posts')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_post_attachments');
        Schema::dropIfExists('tb_homepage_sections');

        Schema::table('tb_homepage_pages', function (Blueprint $table) {
            $table->dropColumn('subtitle');
        });

        Schema::table('tb_homepage_settings', function (Blueprint $table) {
            $table->dropColumn([
                'hero_eyebrow', 'hero_button_label', 'hero_button_url',
                'announcement_enabled', 'announcement_badge', 'announcement_title', 'announcement_text',
                'announcement_image_path', 'announcement_link_label', 'announcement_link_url', 'announcement_file_path',
                'tiles', 'stats', 'page_headings',
                'contact_text', 'footer_info_title', 'footer_info_text', 'show_member_area_link',
            ]);
        });
    }
};
