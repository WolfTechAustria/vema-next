<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Homepage-Beiträge per E-Mail: eigenes IMAP-Postfach, das regelmäßig
     * abgerufen wird; berechtigte Mails werden zu Beitrags-Entwürfen.
     */
    public function up(): void
    {
        Schema::table('tb_homepage_settings', function (Blueprint $table) {
            $table->boolean('mail_posts_enabled')->default(false);
            $table->string('mail_posts_address', 190)->nullable();
            $table->string('mail_posts_host', 190)->nullable();
            $table->unsignedSmallInteger('mail_posts_port')->nullable();
            $table->string('mail_posts_encryption', 20)->nullable();
            $table->boolean('mail_posts_validate_cert')->default(true);
            $table->string('mail_posts_username', 190)->nullable();
            $table->text('mail_posts_password')->nullable();
            $table->string('mail_posts_folder', 190)->nullable();
            $table->string('mail_posts_processed_folder', 190)->nullable();
            $table->text('mail_posts_allowed_senders')->nullable();
            $table->dateTime('mail_posts_checked_at')->nullable();
            $table->string('mail_posts_last_error', 500)->nullable();
        });

        Schema::table('tb_posts', function (Blueprint $table) {
            $table->string('mail_message_id', 255)->nullable()->index();
            $table->string('submitted_by_email', 190)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tb_posts', function (Blueprint $table) {
            $table->dropIndex(['mail_message_id']);
            $table->dropColumn(['mail_message_id', 'submitted_by_email']);
        });

        Schema::table('tb_homepage_settings', function (Blueprint $table) {
            $table->dropColumn([
                'mail_posts_enabled', 'mail_posts_address', 'mail_posts_host', 'mail_posts_port',
                'mail_posts_encryption', 'mail_posts_validate_cert', 'mail_posts_username', 'mail_posts_password',
                'mail_posts_folder', 'mail_posts_processed_folder', 'mail_posts_allowed_senders',
                'mail_posts_checked_at', 'mail_posts_last_error',
            ]);
        });
    }
};
