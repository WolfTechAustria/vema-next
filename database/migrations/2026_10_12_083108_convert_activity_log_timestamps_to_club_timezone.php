<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bis hier lief die App fest in UTC (config/app.php ignorierte die .env),
     * das Aktivitätsprotokoll wurde daher in UTC gespeichert und um 1–2 Stunden
     * zu früh angezeigt. Ab jetzt läuft die App in der Vereinszeitzone — die
     * bestehenden Einträge werden einmalig umgerechnet (inkl. Sommerzeit).
     */
    public function up(): void
    {
        $this->convert('UTC', config('app.timezone'));
    }

    public function down(): void
    {
        $this->convert(config('app.timezone'), 'UTC');
    }

    private function convert(string $from, string $to): void
    {
        if ($from === $to || ! Schema::hasTable('tb_activity_log')) {
            return;
        }

        DB::table('tb_activity_log')
            ->select(['activityLogID', 'created_at', 'updated_at'])
            ->orderBy('activityLogID')
            ->chunkById(500, function ($entries) use ($from, $to) {
                foreach ($entries as $entry) {
                    DB::table('tb_activity_log')
                        ->where('activityLogID', $entry->activityLogID)
                        ->update([
                            'created_at' => $this->shift($entry->created_at, $from, $to),
                            'updated_at' => $this->shift($entry->updated_at, $from, $to),
                        ]);
                }
            }, 'activityLogID');
    }

    private function shift(?string $timestamp, string $from, string $to): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        return CarbonImmutable::parse($timestamp, $from)->setTimezone($to)->format('Y-m-d H:i:s');
    }
};
