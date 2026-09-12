<?php

namespace App\Mail;

use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberMagicLoginMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Member $member,
        public string $token
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Dein Login-Link zum Mitgliederbereich'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.member-magic-login',
            with: [
                'loginUrl' => route(
                    'member.magic-login',
                    ['token' => $this->token]
                ),
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
