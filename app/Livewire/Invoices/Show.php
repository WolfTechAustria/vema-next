<?php

namespace App\Livewire\Invoices;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Services\ActivityLogger;
use App\Services\ImapSentMailService;
use App\Services\InvoiceFinalizer;
use App\Services\InvoiceReminderService;
use Livewire\Component;

class Show extends Component
{
    public Invoice $invoice;

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice->load(['items', 'recipient', 'creator', 'reminders']);
    }

    public function finalize(): void
    {
        $invoice = app(InvoiceFinalizer::class)->finalize($this->invoice);

        ActivityLogger::log(
            'invoice.finalized',
            'Rechnung '.$invoice->invoice_number.' wurde erstellt.',
            'Invoice',
            $invoice->invoiceID
        );

        session()->flash('success', 'Rechnung '.$invoice->invoice_number.' wurde erstellt.');

        $this->invoice = $invoice->load(['items', 'recipient', 'creator']);
    }

    public function sendEmail(): void
    {
        if (! $this->invoice->file_path) {
            session()->flash('error', 'Für diese Rechnung liegt noch kein PDF vor.');

            return;
        }

        if (! $this->invoice->recipient?->email) {
            session()->flash('error', 'Für diesen Empfänger ist keine E-Mail-Adresse hinterlegt.');

            return;
        }

        app(ImapSentMailService::class)->sendAndAppend(
            new InvoiceMail($this->invoice),
            $this->invoice->recipient->email,
            ['invoiceID' => $this->invoice->invoiceID]
        );

        $this->invoice->update([
            'sent_at' => now(),
        ]);

        ActivityLogger::log(
            'invoice.sent',
            'Rechnung '.$this->invoice->invoice_number.' wurde per E-Mail an '.$this->invoice->recipient->email.' versendet.',
            'Invoice',
            $this->invoice->invoiceID
        );

        session()->flash('success', 'Rechnung wurde per E-Mail versendet.');
    }

    public function sendReminderEmail(): void
    {
        $this->sendReminder(InvoiceReminderService::EMAIL);
    }

    public function createReminderPdf()
    {
        $reminder = $this->sendReminder(InvoiceReminderService::POST);

        if ($reminder) {
            return redirect()->route('invoice-reminders.pdf', $reminder);
        }
    }

    private function sendReminder(string $deliveryMethod): ?InvoiceReminder
    {
        try {
            $reminder = app(InvoiceReminderService::class)->send($this->invoice, $deliveryMethod);
        } catch (\RuntimeException $exception) {
            session()->flash('error', $exception->getMessage());

            return null;
        }

        $this->invoice->load('reminders');

        session()->flash('success', $reminder->level_label.($deliveryMethod === InvoiceReminderService::EMAIL
            ? ' wurde per E-Mail versendet.'
            : ' wurde als PDF erzeugt.'));

        return $reminder;
    }

    public function markPaid(): void
    {
        $this->invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        ActivityLogger::log(
            'invoice.paid',
            'Rechnung '.$this->invoice->invoice_number.' wurde als bezahlt markiert.',
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
            'Rechnung '.($this->invoice->invoice_number ?? '#'.$this->invoice->invoiceID).' wurde storniert.',
            'Invoice',
            $this->invoice->invoiceID
        );

        session()->flash('success', 'Rechnung wurde storniert.');
    }

    public function render()
    {
        return view('livewire.invoices.show')->layout('layouts.app', [
            'title' => 'Rechnung '.($this->invoice->invoice_number ?? '#'.$this->invoice->invoiceID).' | VEMA',
            'heading' => 'Rechnung '.($this->invoice->invoice_number ?? 'Entwurf'),
        ]);
    }
}
