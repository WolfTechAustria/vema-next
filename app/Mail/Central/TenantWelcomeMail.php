<?php

namespace App\Mail\Central;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $passwordSetupUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Willkommen bei VEMA — '.$this->tenant->name.' ist eingerichtet',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.central.tenant-welcome',
        );
    }
}
