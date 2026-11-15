<?php

namespace App\Mail;

use App\Mail\Contracts\ExemptFromMailQuota;
use App\Models\VemaNews;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Neuigkeit zu VEMA an die Admins einer eigenen Installation.
 */
class VemaNewsMail extends Mailable implements ExemptFromMailQuota
{
    use Queueable, SerializesModels;

    public function __construct(public VemaNews $news) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Neu in VEMA: '.$this->news->title);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.vema-news',
            with: ['newsUrl' => route('news.index')]
        );
    }
}
