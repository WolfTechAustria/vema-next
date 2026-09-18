<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BirthdayListMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $monthName;
    public int $memberCount;
    public string $pdfContent;
    public string $pdfFileName;

    public function __construct(
        string $monthName,
        int $memberCount,
        string $pdfContent,
        string $pdfFileName
    ) {
        $this->monthName = $monthName;
        $this->memberCount = $memberCount;
        $this->pdfContent = $pdfContent;
        $this->pdfFileName = $pdfFileName;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Geburtstagsliste ' . $this->monthName
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.birthday-list'
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => $this->pdfContent,
                $this->pdfFileName
            )->withMime('application/pdf'),
        ];
    }
}
