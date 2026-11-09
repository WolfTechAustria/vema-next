<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Online-Mitgliedsanmeldung (Einstellungen → Online-Anmeldung).
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->boolean('application_enabled')->default(false);
            $table->text('application_intro')->nullable();
            $table->text('application_privacy_text')->nullable();
            // email | captcha | both
            $table->string('application_spam_protection', 20)->default('email');
            $table->boolean('application_signature')->default(true);
            $table->json('application_notify_emails')->nullable();
            $table->json('application_fields')->nullable();
            $table->json('application_membership_types')->nullable();
            $table->json('application_consents')->nullable();
        });

        Schema::table('tb_member_fields', function (Blueprint $table) {
            $table->boolean('on_application')->default(false)->after('portal');
        });
    }

    public function down(): void
    {
        Schema::table('tb_member_fields', function (Blueprint $table) {
            $table->dropColumn('on_application');
        });

        Schema::table('tb_settings', function (Blueprint $table) {
            $table->dropColumn([
                'application_enabled',
                'application_intro',
                'application_privacy_text',
                'application_spam_protection',
                'application_signature',
                'application_notify_emails',
                'application_fields',
                'application_membership_types',
                'application_consents',
            ]);
        });
    }
};
