<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Vorschreibungen und Erinnerungen setzen diese beiden Vorlagen voraus.
     * Neue Vereine bekommen neutrale Standardtexte; bestehende Vorlagen
     * (z. B. bei Angerberg) bleiben unverändert.
     */
    public function up(): void
    {
        $now = now();

        DB::table('tb_templates')->insertOrIgnore([
            [
                'key' => 'membership_fee_prescription',
                'name' => 'Mitgliedsbeitragsvorschreibung',
                'subject' => 'Mitgliedsbeitrag {{year}}',
                'body_html' => '<p>Hallo {{first_name}},</p>'
                    .'<p>ein neues Vereinsjahr hat begonnen — herzlichen Dank, dass du weiterhin dabei bist!</p>'
                    .'<p>Bitte überweise deinen Mitgliedsbeitrag für {{year}} in Höhe von <strong>€ {{amount}}</strong> bis {{due_date}}.</p>'
                    .'<p>Vielen Dank für deine Unterstützung!</p>',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'membership_fee_reminder',
                'name' => 'Mitgliedsbeitrag Erinnerung',
                'subject' => '{{reminder_level}}. Erinnerung Mitgliedsbeitrag {{year}}',
                'body_html' => '<p>Hallo {{first_name}},</p>'
                    .'<p>wir möchten dich daran erinnern, dass dein Mitgliedsbeitrag für {{year}} in Höhe von <strong>€ {{amount}}</strong> noch offen ist.</p>'
                    .'<p>Bitte überweise den offenen Betrag auf unser Vereinskonto.</p>'
                    .'<p>Falls du den Beitrag inzwischen bereits überwiesen hast, betrachte dieses Schreiben bitte als gegenstandslos.</p>',
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
