<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    /**
     * Einziger klassischer Controller-Endpunkt der Rechnungsfunktion: liefert
     * das gespeicherte PDF als rohe HTTP-Antwort aus. Alle anderen Aktionen
     * (finalisieren, versenden, als bezahlt markieren, stornieren) laufen
     * direkt über die Livewire-Komponente App\Livewire\Invoices\Show, damit
     * dafür keine zusätzlichen, separat zu pflegenden Routen nötig sind.
     */
    public function downloadPdf(Invoice $invoice)
    {
        abort_unless($invoice->file_path, 404, 'Für diese Rechnung wurde noch kein PDF erzeugt.');

        $disk = Storage::disk('local');

        abort_unless($disk->exists($invoice->file_path), 404, 'PDF-Datei nicht gefunden.');

        return response(
            $disk->get($invoice->file_path),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $invoice->file_name . '"',
            ]
        );
    }
}
