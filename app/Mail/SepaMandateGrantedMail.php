<?php

namespace App\Mail;

use App\Mail\Contracts\ExemptFromMailQuota;
use App\Models\SepaMandate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Hinweis an Admins und Kassiere: Mitglied hat online ein SEPA-Mandat
 * erteilt oder widerrufen.
 */
class SepaMandateGrantedMail extends Mailable implements ExemptFromMailQuota
{
    use Queueable, SerializesModels;

    public function __construct(public SepaMandate $mandate) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->mandate->isActive() ? 'Neues SEPA-Mandat: ' : 'SEPA-Mandat widerrufen: ').$this->mandate->member?->full_name);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sepa.mandate-granted',
            with: ['memberUrl' => route('members.show', $this->mandate->memberID)]
        );
    }
}
