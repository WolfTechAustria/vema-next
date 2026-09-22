<?php

namespace App\Services;

use App\Models\InvoiceNumberSequence;
use Illuminate\Support\Facades\DB;

class InvoiceNumberGenerator
{
    /**
     * Vergibt atomar die nächste Rechnungsnummer des laufenden Jahres im
     * Format "{Jahr}-{4-stellig}" (z. B. "2026-0001"). Muss innerhalb einer
     * DB-Transaktion aufgerufen werden, die auch das Speichern der Rechnung
     * enthält, sonst ist die Sperre wirkungslos.
     */
    public function next(?int $year = null): string
    {
        $year ??= (int) now()->year;

        $sequence = InvoiceNumberSequence::query()
            ->lockForUpdate()
            ->where('year', $year)
            ->first();

        if (!$sequence) {
            $sequence = InvoiceNumberSequence::create([
                'year' => $year,
                'next_number' => 1,
            ]);
        }

        $number = $sequence->next_number;

        $sequence->update([
            'next_number' => $number + 1,
        ]);

        return sprintf('%d-%04d', $year, $number);
    }
}
