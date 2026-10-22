<?php

namespace App\Mail;

use App\Models\InvoiceReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class InvoiceReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public InvoiceReminder $reminder
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->reminder->level_label.' zu Rechnung '.$this->reminder->invoice->invoice_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-reminder',
            with: [
                'reminder' => $this->reminder,
                'invoice' => $this->reminder->invoice,
            ]
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [
            Attachment::fromData(
                fn () => Storage::disk('local')->get($this->reminder->file_path),
                $this->reminder->file_name
            )->withMime('application/pdf'),
        ];

        $invoice = $this->reminder->invoice;

        if ($invoice->file_path) {
            $attachments[] = Attachment::fromData(
                fn () => Storage::disk('local')->get($invoice->file_path),
                $invoice->file_name
            )->withMime('application/pdf');
        }

        return $attachments;
    }
}
