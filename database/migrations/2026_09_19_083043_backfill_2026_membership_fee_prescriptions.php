<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {

            /*
             * Beitragsjahr 2026 ermitteln.
             */
            $year = DB::table('tb_membership_fee_years')
                ->where('year', 2026)
                ->first();

            if (!$year) {
                return;
            }

            /*
             * Historisches Versanddatum.
             *
             * Wenn ein Fälligkeitsdatum vorhanden ist, verwenden wir
             * dieses als plausiblen historischen Bezugspunkt.
             *
             * Andernfalls 01.01.2026.
             */
            $historicalSentAt =
                $year->due_date
                    ?: '2026-01-01 00:00:00';

            /*
             * Alle Beitrags-Einträge von 2026 laden.
             */
            $entries = DB::table('tb_membership_fee_entries')
                ->where('yearID', $year->yearID)
                ->get();

            foreach ($entries as $entry) {

                /*
                 * Bereits vorhandene Vorschreibung nicht doppelt anlegen.
                 */
                $exists = DB::table('tb_membership_fee_prescriptions')
                    ->where('entryID', $entry->entryID)
                    ->where('type', 'prescription')
                    ->exists();

                if ($exists) {
                    continue;
                }

                /*
                 * Erste bekannte E-Mail-Adresse des Mitglieds übernehmen,
                 * falls vorhanden.
                 */
                $email = DB::table('tb_email')
                    ->where('memberID', $entry->memberID)
                    ->value('email');

                DB::table('tb_membership_fee_prescriptions')
                    ->insert([
                        'entryID' => $entry->entryID,
                        'memberID' => $entry->memberID,
                        'yearID' => $year->yearID,

                        'type' => 'prescription',
                        'reminder_level' => null,

                        /*
                         * Legacy-Vorschreibung:
                         * Es gibt im neuen System keine gespeicherte PDF-Datei.
                         */
                        'file_path' => '/',
                        'file_name' => 'Vorschreibung 2026',

                        'sent_to' => $email,
                        'sent_at' => $historicalSentAt,

                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function down(): void
    {
        /*
         * Nur die von dieser Migration erzeugten Legacy-Datensätze entfernen.
         *
         * Achtung:
         * Da wir keinen eigenen Legacy-Marker haben, löschen wir nur
         * Vorschreibungen aus 2026 ohne PDF-Datei.
         */
        $yearID = DB::table('tb_membership_fee_years')
            ->where('year', 2026)
            ->value('yearID');

        if (!$yearID) {
            return;
        }

        DB::table('tb_membership_fee_prescriptions')
            ->where('yearID', $yearID)
            ->where('type', 'prescription')
            ->whereNull('file_path')
            ->whereNull('file_name')
            ->delete();
    }
};
