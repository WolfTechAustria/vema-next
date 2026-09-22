<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Rechnung ' . $this->invoice->invoice_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
            with: [
                'invoice' => $this->invoice,
            ]
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => Storage::disk('local')->get($this->invoice->file_path),
                $this->invoice->file_name
            )->withMime('application/pdf'),
        ];
    }
}
