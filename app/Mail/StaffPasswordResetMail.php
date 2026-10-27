<?php

namespace App\Mail;

use App\Mail\Contracts\ExemptFromMailQuota;
use App\Models\User;
use App\Services\WelcomeMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Passwort-Link für Funktionäre. Bei einem neu angelegten Zugang ist das
 * zugleich die Willkommens-E-Mail (Text aus dem Template „staff_welcome“).
 */
class StaffPasswordResetMail extends Mailable implements ExemptFromMailQuota
{
    use Queueable, SerializesModels;

    /**
     * @var array{subject: string, body: string}|null
     */
    private ?array $welcome = null;

    public function __construct(
        public User $user,
        public string $token,
        public bool $isNewAccount = false
    ) {
        if ($isNewAccount) {
            $this->welcome = app(WelcomeMailService::class)->staffWelcome($user);
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->welcome['subject'] ?? 'Passwort zurücksetzen'
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
                'loginUrl' => route('login'),
                'welcomeHtml' => $this->welcome['body'] ?? null,
                'heading' => $this->welcome['subject'] ?? 'Passwort zurücksetzen',
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
