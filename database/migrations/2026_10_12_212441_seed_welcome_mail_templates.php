<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Texte der Willkommens-E-Mails für neue Mitglieder und neue
     * Funktionäre, unter Kommunikation → Templates anpassbar. Links (Portal,
     * WhatsApp, Login, Passwort) fügt die E-Mail selbst unter dem Text an.
     */
    public function up(): void
    {
        $now = now();

        DB::table('tb_templates')->insertOrIgnore([
            [
                'key' => 'member_welcome',
                'name' => 'Willkommen – neues Mitglied',
                'subject' => 'Willkommen bei {{club_name}}',
                'body_html' => '<p>Hallo {{first_name}},</p>'
                    .'<p>herzlich willkommen bei {{club_name}}! Schön, dass du dabei bist.</p>'
                    .'<p>Unten findest du alles, was du für den Start brauchst. Bei Fragen melde dich jederzeit beim Vorstand.</p>',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'staff_welcome',
                'name' => 'Willkommen – neuer Funktionärszugang',
                'subject' => 'Dein Zugang zur Vereinsverwaltung von {{club_name}}',
                'body_html' => '<p>Hallo {{first_name}},</p>'
                    .'<p>für dich wurde ein Zugang zur Vereinsverwaltung von {{club_name}} angelegt. '
                    .'Damit kannst du – je nach deinen Berechtigungen – Mitglieder, Beiträge, Termine, Rundschreiben und mehr verwalten.</p>'
                    .'<p>Lege zuerst über den Button unten dein Passwort fest. Danach meldest du dich jederzeit über die Login-Seite an.</p>',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        // Vorlagen können inzwischen angepasst worden sein — nicht löschen.
    }
};
