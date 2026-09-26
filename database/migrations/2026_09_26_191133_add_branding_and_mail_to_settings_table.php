<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->string('letter_place', 100)->nullable();

            $table->string('logo_path')->nullable();
            $table->string('letterhead_path')->nullable();

            $table->string('signatory_1_title', 100)->nullable();
            $table->string('signatory_1_name', 100)->nullable();
            $table->string('signatory_1_image_path')->nullable();
            $table->string('signatory_2_title', 100)->nullable();
            $table->string('signatory_2_name', 100)->nullable();
            $table->string('signatory_2_image_path')->nullable();

            $table->string('mail_from_address', 150)->nullable();
            $table->string('mail_from_name', 150)->nullable();

            $table->string('imap_host', 150)->nullable();
            $table->unsignedSmallInteger('imap_port')->nullable();
            $table->string('imap_encryption', 20)->nullable();
            $table->boolean('imap_validate_cert')->default(true);
            $table->string('imap_username', 150)->nullable();
            $table->text('imap_password')->nullable();
            $table->string('imap_sent_folder', 150)->nullable();

            $table->string('birthday_recipient_group', 150)->nullable();
        });

        $this->importLegacyBranding();
    }

    /**
     * Bestehende Installation (Einstellungen-Zeile existiert bereits): die
     * bisher fest im Code hinterlegten Werte und Dateien übernehmen, damit
     * Briefe und Mails unverändert aussehen. Neue Installationen haben zu
     * diesem Zeitpunkt keine Zeile und starten ohne Branding.
     */
    private function importLegacyBranding(): void
    {
        $setting = DB::table('tb_settings')->first();

        if (! $setting) {
            return;
        }

        $legacyFiles = [
            'logo_path' => [public_path('images/logo.jpg'), 'logo.jpg'],
            'letterhead_path' => [storage_path('app/templates/briefpapier.pdf'), 'letterhead.pdf'],
            'signatory_1_image_path' => [public_path('images/letterhead/signatureOSM.JPG'), 'signature-1.jpg'],
            'signatory_2_image_path' => [public_path('images/letterhead/signatureSF.JPG'), 'signature-2.jpg'],
        ];

        $updates = [
            'letter_place' => $setting->city,
            'signatory_1_title' => 'Der Oberschützenmeister',
            'signatory_1_name' => 'OBERHAUSER Wolfgang',
            'signatory_2_title' => 'Der Schriftführer',
            'signatory_2_name' => 'OBRIST Wolfgang',
        ];

        $disk = Storage::disk('branding');

        foreach ($legacyFiles as $column => [$sourcePath, $targetPath]) {
            if (is_file($sourcePath) && $disk->put($targetPath, file_get_contents($sourcePath))) {
                $updates[$column] = $targetPath;
            }
        }

        DB::table('tb_settings')
            ->where('settingsID', $setting->settingsID)
            ->update($updates);
    }

    public function down(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->dropColumn([
                'letter_place',
                'logo_path',
                'letterhead_path',
                'signatory_1_title',
                'signatory_1_name',
                'signatory_1_image_path',
                'signatory_2_title',
                'signatory_2_name',
                'signatory_2_image_path',
                'mail_from_address',
                'mail_from_name',
                'imap_host',
                'imap_port',
                'imap_encryption',
                'imap_validate_cert',
                'imap_username',
                'imap_password',
                'imap_sent_folder',
                'birthday_recipient_group',
            ]);
        });
    }
};
