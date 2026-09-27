<?php

namespace App\Mail\Central;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Hinweis an den Plattform-Betreiber (neue Registrierung, Einrichtung).
 */
class PlatformNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $event,
        public ?string $details = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[VEMA-Plattform] '.$this->event.': '.$this->tenant->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.central.platform-notification',
        );
    }
}
