<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceNumberSequence;
use App\Models\Setting;

/**
 * Rechnungsnummern nach dem in den Einstellungen hinterlegten Nummernkreis.
 *
 * Platzhalter: {JAHR} (2026), {JJ} (26), {NR} (fortlaufende Nummer) bzw.
 * {NR:5} (mit führenden Nullen auf 5 Stellen). Enthält das Format ein
 * Jahr, beginnt der Zähler jedes Jahr neu; sonst läuft er durchgehend
 * weiter (Zähler-Zeile mit Jahr 0).
 */
class InvoiceNumberGenerator
{
    public const DEFAULT_FORMAT = '{JAHR}-{NR:4}';

    /**
     * tb_invoices.invoice_number ist auf 20 Zeichen begrenzt.
     */
    public const MAX_LENGTH = 20;

    private const CONTINUOUS_COUNTER_KEY = 0;

    /**
     * Vergibt atomar die nächste freie Rechnungsnummer. Muss innerhalb einer
     * DB-Transaktion aufgerufen werden, die auch das Speichern der Rechnung
     * enthält, sonst ist die Sperre wirkungslos.
     */
    public function next(?int $year = null): string
    {
        $year ??= (int) now()->year;
        $format = $this->currentFormat();
        $counterKey = $this->counterKey($format, $year);

        $sequence = InvoiceNumberSequence::query()
            ->lockForUpdate()
            ->where('year', $counterKey)
            ->first();

        if (! $sequence) {
            $sequence = InvoiceNumberSequence::create([
                'year' => $counterKey,
                'next_number' => 1,
            ]);
        }

        $number = $sequence->next_number;
        $invoiceNumber = $this->format($format, $year, $number);

        // Bereits vergebene Nummern überspringen (z. B. nach Umstellung des
        // Nummernkreises oder wenn die nächste Nummer zu niedrig gesetzt wurde).
        while (Invoice::query()->where('invoice_number', $invoiceNumber)->exists()) {
            $number++;
            $invoiceNumber = $this->format($format, $year, $number);
        }

        $sequence->update([
            'next_number' => $number + 1,
        ]);

        return $invoiceNumber;
    }

    public function format(string $format, int $year, int $number): string
    {
        $formatted = str_replace(
            ['{JAHR}', '{JJ}'],
            [(string) $year, substr((string) $year, -2)],
            $format
        );

        return preg_replace_callback(
            '/\{NR(?::(\d{1,2}))?\}/',
            fn (array $match) => str_pad((string) $number, (int) ($match[1] ?? 0), '0', STR_PAD_LEFT),
            $formatted
        );
    }

    /**
     * Fehlermeldung für ein ungültiges Format, sonst null.
     */
    public function validationError(string $format): ?string
    {
        if (preg_match_all('/\{NR(?::\d{1,2})?\}/', $format) !== 1) {
            return 'Das Format muss genau einmal {NR} (oder z. B. {NR:4}) enthalten.';
        }

        $withoutPlaceholders = preg_replace('/\{JAHR\}|\{JJ\}|\{NR(?::\d{1,2})?\}/', '', $format);

        if (str_contains($withoutPlaceholders, '{') || str_contains($withoutPlaceholders, '}')) {
            return 'Erlaubte Platzhalter sind {JAHR}, {JJ}, {NR} und {NR:Stellen}.';
        }

        if (! preg_match('/^[A-Za-z0-9\-_\/.]*$/', $withoutPlaceholders)) {
            return 'Außer den Platzhaltern sind nur Buchstaben, Ziffern und - _ / . erlaubt.';
        }

        return null;
    }

    public function usesYearlyCounter(string $format): bool
    {
        return str_contains($format, '{JAHR}') || str_contains($format, '{JJ}');
    }

    /**
     * Nächste Nummer des Zählers, der für das Format im angegebenen Jahr gilt.
     */
    public function nextNumberFor(string $format, int $year): int
    {
        return (int) (InvoiceNumberSequence::query()
            ->where('year', $this->counterKey($format, $year))
            ->value('next_number') ?? 1);
    }

    public function setNextNumber(string $format, int $year, int $nextNumber): void
    {
        InvoiceNumberSequence::query()->updateOrCreate(
            ['year' => $this->counterKey($format, $year)],
            ['next_number' => $nextNumber],
        );
    }

    public function currentFormat(): string
    {
        return Setting::current()->invoice_number_format ?: self::DEFAULT_FORMAT;
    }

    private function counterKey(string $format, int $year): int
    {
        return $this->usesYearlyCounter($format) ? $year : self::CONTINUOUS_COUNTER_KEY;
    }
}
