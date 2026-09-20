<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $token,
        public bool $isNewAccount = false
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isNewAccount
                ? 'Zugang zu VEMA freigeschaltet — Passwort festlegen'
                : 'Passwort zurücksetzen'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.staff-password-reset',
            with: [
                'resetUrl' => route('password.reset', [
                    'token' => $this->token,
                    'email' => $this->user->email,
                ]),
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
