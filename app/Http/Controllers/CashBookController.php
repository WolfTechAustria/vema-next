<?php

namespace App\Http\Controllers;

use App\Models\CashBookAttachment;
use App\Models\CashBookEntry;
use App\Models\CashBookYear;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashBookController extends Controller
{
    /**
     * Liefert einen Beleg aus dem privaten Speicher inline aus (Bild/PDF).
     */
    public function showAttachment(CashBookAttachment $attachment): StreamedResponse
    {
        abort_unless(
            $attachment->file_path
            && Storage::disk('local')->exists($attachment->file_path),
            404
        );

        return Storage::disk('local')->response(
            $attachment->file_path,
            $attachment->file_name
        );
    }

    /**
     * Kassabericht eines Vereinsjahres für die Jahreshauptversammlung:
     * Summen je Kategorie und chronologische Buchungsliste mit laufendem Saldo.
     */
    public function report(CashBookYear $year): Response
    {
        $entries = $year->entries()
            ->withCount('attachments')
            ->orderBy('date')
            ->orderBy('receipt_number')
            ->get();

        $runningBalance = (float) $year->opening_balance;

        $rows = $entries->map(function (CashBookEntry $entry) use (&$runningBalance) {
            $runningBalance = round($runningBalance + $entry->signed_amount, 2);

            return [
                'entry' => $entry,
                'balance' => $runningBalance,
            ];
        });

        $categorySummary = $entries
            ->groupBy(fn (CashBookEntry $entry) => $entry->type->value)
            ->map(fn ($group) => $group
                ->groupBy(fn (CashBookEntry $entry) => $entry->category ?: 'Ohne Kategorie')
                ->map(fn ($categoryEntries) => round($categoryEntries->sum(fn (CashBookEntry $entry) => (float) $entry->amount), 2))
                ->sortKeys());

        $totals = $year->totals();

        $pdf = Pdf::loadView('pdf.cash-book-report', [
            'year' => $year,
            'rows' => $rows,
            'categorySummary' => $categorySummary,
            'totals' => $totals,
            'closingBalance' => round((float) $year->opening_balance + $totals['income'] - $totals['expense'], 2),
            'settings' => Setting::current(),
        ])->setPaper('a4', 'portrait');

        $pdf->render();

        $domPdf = $pdf->getDomPDF();

        $domPdf->getCanvas()->page_text(
            270,
            810,
            'Seite {PAGE_NUM} / {PAGE_COUNT}',
            $domPdf->getFontMetrics()->getFont('DejaVu Sans', 'normal'),
            7,
            [0.4, 0.4, 0.4]
        );

        return response(
            $pdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="Kassabericht_'.Str::slug($year->name).'.pdf"',
            ]
        );
    }
}
