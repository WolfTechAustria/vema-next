<?php

namespace App\Mail;

use App\Mail\Contracts\ExemptFromMailQuota;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Einladung eines Verbands an einen Verein; angenommen wird in den
 * Vereinseinstellungen unter „Verbände“.
 */
class FederationInvitationMail extends Mailable implements ExemptFromMailQuota
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $federation,
        public Tenant $club,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Einladung: '.$this->federation->name.' auf VEMA',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.federation.invitation',
            with: [
                'acceptUrl' => $this->club->url().route('admin.settings.federations', absolute: false),
            ],
        );
    }
}
