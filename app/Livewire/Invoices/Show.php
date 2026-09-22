<?php

namespace App\Livewire\Invoices;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Services\ActivityLogger;
use App\Services\InvoiceFinalizer;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class Show extends Component
{
    public Invoice $invoice;

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice->load(['items', 'recipient', 'creator']);
    }

    public function finalize(): void
    {
        $invoice = app(InvoiceFinalizer::class)->finalize($this->invoice);

        ActivityLogger::log(
            'invoice.finalized',
            'Rechnung ' . $invoice->invoice_number . ' wurde erstellt.',
            'Invoice',
            $invoice->invoiceID
        );

        session()->flash('success', 'Rechnung ' . $invoice->invoice_number . ' wurde erstellt.');

        $this->invoice = $invoice->load(['items', 'recipient', 'creator']);
    }

    public function sendEmail(): void
    {
        if (!$this->invoice->file_path) {
            session()->flash('error', 'Für diese Rechnung liegt noch kein PDF vor.');

            return;
        }

        if (!$this->invoice->recipient?->email) {
            session()->flash('error', 'Für diesen Empfänger ist keine E-Mail-Adresse hinterlegt.');

            return;
        }

        Mail::to($this->invoice->recipient->email)->send(new InvoiceMail($this->invoice));

        $this->invoice->update([
            'sent_at' => now(),
        ]);

        ActivityLogger::log(
            'invoice.sent',
            'Rechnung ' . $this->invoice->invoice_number . ' wurde per E-Mail an ' . $this->invoice->recipient->email . ' versendet.',
            'Invoice',
            $this->invoice->invoiceID
        );

        session()->flash('success', 'Rechnung wurde per E-Mail versendet.');
    }

    public function markPaid(): void
    {
        $this->invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        ActivityLogger::log(
            'invoice.paid',
            'Rechnung ' . $this->invoice->invoice_number . ' wurde als bezahlt markiert.',
            'Invoice',
            $this->invoice->invoiceID
        );

        session()->flash('success', 'Rechnung wurde als bezahlt markiert.');
    }

    public function cancel(): void
    {
        $this->invoice->update([
            'status' => 'canceled',
        ]);

        ActivityLogger::log(
            'invoice.canceled',
            'Rechnung ' . ($this->invoice->invoice_number ?? '#' . $this->invoice->invoiceID) . ' wurde storniert.',
            'Invoice',
            $this->invoice->invoiceID
        );

        session()->flash('success', 'Rechnung wurde storniert.');
    }

    public function render()
    {
        return view('livewire.invoices.show')->layout('layouts.app', [
            'title' => 'Rechnung ' . ($this->invoice->invoice_number ?? '#' . $this->invoice->invoiceID) . ' | VEMA',
            'heading' => 'Rechnung ' . ($this->invoice->invoice_number ?? 'Entwurf'),
        ]);
    }
}
