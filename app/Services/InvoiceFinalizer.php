<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Vergibt die Rechnungsnummer und erzeugt/speichert das PDF beim Übergang
 * eines Entwurfs in den finalisierten Zustand. Wird sowohl vom
 * InvoiceController (eigenständige Aktion) als auch direkt aus der
 * "Rechnung erstellen"-Livewire-Komponente aufgerufen.
 */
class InvoiceFinalizer
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numberGenerator
    ) {}

    public function finalize(Invoice $invoice): Invoice
    {
        abort_unless($invoice->isDraft(), 422, 'Diese Rechnung wurde bereits finalisiert.');

        $invoice->load(['items', 'recipient']);

        abort_if($invoice->items->isEmpty(), 422, 'Eine Rechnung ohne Positionen kann nicht finalisiert werden.');

        return DB::transaction(function () use ($invoice) {
            $invoiceNumber = $this->numberGenerator->next(
                (int) $invoice->invoice_date->format('Y')
            );

            $invoice->update([
                'invoice_number' => $invoiceNumber,
                'status' => 'sent',
            ]);

            $this->generateAndStorePdf($invoice->fresh(['items', 'recipient']));

            return $invoice->fresh();
        });
    }

    private function generateAndStorePdf(Invoice $invoice): void
    {
        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'recipient' => $invoice->recipient,
            'items' => $invoice->items,
            'settings' => Setting::current(),
        ])->setPaper('a4', 'portrait');

        $finalPdf = app(PdfLetterheadService::class)
            ->applyClubLetterhead($pdf->output());

        $filename = 'Rechnung_'.$invoice->invoice_number.'.pdf';
        $storagePath = 'invoices/'.$invoice->invoice_date->format('Y').'/'.$filename;

        Storage::disk('local')->put($storagePath, $finalPdf);

        $invoice->update([
            'file_path' => $storagePath,
            'file_name' => $filename,
        ]);
    }
}
