<?php

namespace App\Mail;

use App\Mail\Contracts\ExemptFromMailQuota;
use App\Models\User;
use App\Services\StaffLoginEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sicherheitshinweis an die bisherige Adresse: Die Login-E-Mail des
 * Zugangs wurde geändert.
 */
class StaffLoginEmailChangedMail extends Mailable implements ExemptFromMailQuota
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $newEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Login-E-Mail für VEMA wurde geändert');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.staff-login-email-changed',
            with: [
                'maskedEmail' => StaffLoginEmail::mask($this->newEmail),
                'changedAt' => now()->format('d.m.Y, H:i'),
                'loginUrl' => route('login'),
            ]
        );
    }
}
