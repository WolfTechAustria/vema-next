<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hinweise auf der Homepage: Cookie-Banner und ein Popup für wichtige
     * Informationen auf der Startseite.
     */
    public function up(): void
    {
        Schema::table('tb_homepage_settings', function (Blueprint $table) {
            $table->boolean('cookie_banner_enabled')->default(true);
            $table->text('cookie_banner_text')->nullable();
            $table->unsignedBigInteger('cookie_banner_page_id')->nullable();

            $table->boolean('popup_enabled')->default(false);
            $table->string('popup_title', 200)->nullable();
            $table->longText('popup_html')->nullable();
            $table->string('popup_image_path', 255)->nullable();
            $table->string('popup_button_label', 80)->nullable();
            $table->string('popup_button_url', 255)->nullable();
            $table->dateTime('popup_starts_at')->nullable();
            $table->dateTime('popup_ends_at')->nullable();
            $table->string('popup_frequency', 20)->default('once');
            $table->unsignedInteger('popup_version')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('tb_homepage_settings', function (Blueprint $table) {
            $table->dropColumn([
                'cookie_banner_enabled', 'cookie_banner_text', 'cookie_banner_page_id',
                'popup_enabled', 'popup_title', 'popup_html', 'popup_image_path', 'popup_button_label',
                'popup_button_url', 'popup_starts_at', 'popup_ends_at', 'popup_frequency', 'popup_version',
            ]);
        });
    }
};
