<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Öffentliche Vereinshomepage: Einstellungen (eine Zeile je Verein),
     * News-Beiträge mit Bildergalerie und frei anlegbare Seiten.
     */
    public function up(): void
    {
        Schema::create('tb_homepage_settings', function (Blueprint $table) {
            $table->bigIncrements('homepageSettingID');

            $table->boolean('enabled')->default(false);
            $table->string('template', 30)->default('klassisch');
            $table->string('primary_color', 7)->default('#1d4ed8');

            $table->string('hero_title', 200)->nullable();
            $table->text('hero_text')->nullable();
            $table->string('hero_image_path', 255)->nullable();

            $table->longText('about_html')->nullable();

            $table->boolean('show_board')->default(true);
            $table->boolean('show_board_photos')->default(false);
            $table->boolean('contact_visible')->default(true);

            // Eigene Domain im Modus "single"; im Modus "multi" in tenant_domains.
            $table->string('domain', 190)->nullable();
            $table->string('meta_description', 300)->nullable();

            $table->timestamps();
        });

        Schema::create('tb_posts', function (Blueprint $table) {
            $table->bigIncrements('postID');

            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body_html')->nullable();

            $table->string('status', 20)->default('draft');
            $table->dateTime('published_at')->nullable();

            $table->integer('created_by')->nullable();

            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('tb_post_images', function (Blueprint $table) {
            $table->bigIncrements('postImageID');

            $table->unsignedBigInteger('postID');
            $table->string('file_path', 255);
            $table->string('thumb_path', 255);
            $table->string('caption', 255)->nullable();
            $table->unsignedInteger('sort')->default(0);

            $table->timestamps();

            $table->index(['postID', 'sort']);

            $table->foreign('postID')
                ->references('postID')
                ->on('tb_posts')
                ->cascadeOnDelete();
        });

        Schema::create('tb_homepage_pages', function (Blueprint $table) {
            $table->bigIncrements('pageID');

            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->longText('body_html')->nullable();

            $table->unsignedInteger('sort')->default(0);
            $table->boolean('in_menu')->default(true);
            $table->boolean('published')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_homepage_pages');
        Schema::dropIfExists('tb_post_images');
        Schema::dropIfExists('tb_posts');
        Schema::dropIfExists('tb_homepage_settings');
    }
};
