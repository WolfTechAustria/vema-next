<?php

namespace App\Mail\Central;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClubLinksMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Tenant>  $tenants
     */
    public function __construct(
        public Collection $tenants,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Deine Vereine bei VEMA',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.central.club-links',
        );
    }
}
