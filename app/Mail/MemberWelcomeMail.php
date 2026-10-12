<?php

namespace App\Mail;

use App\Models\Member;
use App\Services\WelcomeMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Member $member,
        public string $subjectLine,
        public string $bodyHtml,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        $welcomeMail = app(WelcomeMailService::class);

        return new Content(
            view: 'emails.member-welcome',
            with: [
                'portalUrl' => $welcomeMail->memberPortalAvailable() ? route('member.login') : null,
                'whatsappUrl' => $welcomeMail->whatsappCommunityUrl(),
            ],
        );
    }
}
