<?php

namespace App\Mail\Central;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Neuer Link „Passwort festlegen“ für den Admin-Zugang eines Vereins,
 * vom Plattform-Admin ausgelöst (z. B. Willkommensmail nicht erhalten).
 */
class TenantAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $username,
        public string $passwordSetupUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Dein Zugang zu VEMA — '.$this->tenant->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.central.tenant-access',
        );
    }
}
