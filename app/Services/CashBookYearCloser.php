<?php

namespace App\Services;

use App\Models\CashBookYear;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Schließt ein Vereinsjahr nach der Jahreshauptversammlung ab: Endbestand
 * festschreiben, Jahr sperren und das Folgejahr mit dem Endbestand als
 * Anfangsbestand anlegen (sofern es noch nicht existiert).
 */
class CashBookYearCloser
{
    public function close(
        CashBookYear $year,
        CarbonInterface $generalMeetingDate,
        ?string $note = null,
        ?User $closedBy = null
    ): CashBookYear {
        return DB::transaction(function () use ($year, $generalMeetingDate, $note, $closedBy) {
            $year = CashBookYear::lockForUpdate()->findOrFail($year->cashBookYearID);

            if ($year->isClosed()) {
                throw new RuntimeException('Dieses Vereinsjahr ist bereits abgeschlossen.');
            }

            $closingBalance = $year->balance();

            $year->update([
                'closing_balance' => $closingBalance,
                'general_meeting_date' => $generalMeetingDate->toDateString(),
                'closing_note' => $note ?: null,
                'closed_at' => now(),
                'closed_by' => $closedBy?->id,
            ]);

            $nextYear = CashBookYear::whereDate('start_date', '>', $year->end_date->toDateString())
                ->orderBy('start_date')
                ->first();

            if ($nextYear === null) {
                $start = $year->end_date->copy()->addDay();
                $end = $start->copy()->addYear()->subDay();

                CashBookYear::create([
                    'name' => self::defaultName($start, $end),
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'opening_balance' => $closingBalance,
                ]);
            } elseif (! $nextYear->isClosed()) {
                $nextYear->update(['opening_balance' => $closingBalance]);
            }

            ActivityLogger::log(
                'cash-book.close',
                'Vereinsjahr '.$year->name.' abgeschlossen (Endbestand '
                    .number_format($closingBalance, 2, ',', '.').' €)',
                'cash_book_year',
                $year->cashBookYearID
            );

            return $year->refresh();
        });
    }

    /**
     * Bezeichnung wie "2025/26" bzw. "2025", wenn das Jahr im selben
     * Kalenderjahr endet.
     */
    public static function defaultName(CarbonInterface $start, CarbonInterface $end): string
    {
        return $start->year === $end->year
            ? (string) $start->year
            : $start->format('Y').'/'.$end->format('y');
    }
}
