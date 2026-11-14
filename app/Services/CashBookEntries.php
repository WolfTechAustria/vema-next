<?php

namespace App\Services;

use App\Models\CashBookEntry;
use App\Models\CashBookYear;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Legt Kassabuch-Buchungen mit fortlaufender Belegnummer an.
 */
class CashBookEntries
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(CashBookYear $year, array $attributes): CashBookEntry
    {
        return DB::transaction(function () use ($year, $attributes) {
            CashBookYear::lockForUpdate()->findOrFail($year->cashBookYearID);

            return $year->entries()->create($attributes + [
                'receipt_number' => $year->nextReceiptNumber(),
                'created_by' => Auth::guard('web')->id(),
            ]);
        });
    }

    /**
     * Offenes Kassabuchjahr, in das das Datum fällt.
     */
    public function openYearFor(CarbonInterface $date): ?CashBookYear
    {
        return CashBookYear::query()
            ->open()
            ->get()
            ->first(fn (CashBookYear $year) => $year->containsDate($date));
    }
}
