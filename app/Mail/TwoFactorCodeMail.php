<?php

namespace App\Mail;

use App\Mail\Contracts\ExemptFromMailQuota;
use App\Models\User;
use App\Services\TwoFactorAuthentication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Bestätigungscode für die Zwei-Faktor-Anmeldung per E-Mail.
 */
class TwoFactorCodeMail extends Mailable implements ExemptFromMailQuota
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Ihr Anmeldecode für VEMA: '.$this->code);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.two-factor-code',
            with: [
                'validMinutes' => TwoFactorAuthentication::EMAIL_CODE_MINUTES,
            ]
        );
    }
}
