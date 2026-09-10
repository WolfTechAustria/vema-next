<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MembershipFeePrescriptionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $memberName,
        public int $year,
        public string $pdfContent,
        public string $pdfFilename,
        public string $type = 'prescription',
        public ?int $reminderLevel = null
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = $this->type === 'reminder'
            ? ($this->reminderLevel ?? 1)
            . '. Erinnerung Mitgliedsbeitrag '
            . $this->year
            : 'Mitgliedsbeitrag ' . $this->year;

        return new Envelope(
            subject: $subject
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.membership-fee-prescription',
            with: [
                'memberName' => $this->memberName,
                'year' => $this->year,
                'type' => $this->type,
                'reminderLevel' => $this->reminderLevel,
            ]
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => $this->pdfContent,
                $this->pdfFilename
            )->withMime('application/pdf'),
        ];
    }
}
