<?php

namespace App\Mail;

use App\Models\FederationMessage;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mitteilung eines Verbands an die Kontaktadresse eines Mitgliedsvereins;
 * Anhänge stehen in VEMA unter „Verband“.
 */
class FederationMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $federation,
        public Tenant $club,
        public FederationMessage $federationMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->federation->name.': '.$this->federationMessage->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.federation.message',
            with: [
                'inboxUrl' => $this->club->url().route('federation-inbox', absolute: false),
            ],
        );
    }
}
