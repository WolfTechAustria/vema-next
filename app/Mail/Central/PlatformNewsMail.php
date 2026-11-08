<?php

namespace App\Mail\Central;

use App\Models\PlatformNews;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Neuigkeit zu VEMA an die Kontaktadresse eines Vereins.
 */
class PlatformNewsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PlatformNews $news,
        public Tenant $tenant,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Neu in VEMA: '.$this->news->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.central.platform-news',
            with: [
                'newsUrl' => $this->tenant->url().route('news.index', absolute: false),
            ],
        );
    }
}
