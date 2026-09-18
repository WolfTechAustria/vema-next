<?php

namespace App\Mail;

use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BirthdayReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public Member $member;
    public int $age;
    public bool $isRound;

    public function __construct(
        Member $member,
        int $age,
        bool $isRound
    ) {
        $this->member = $member;
        $this->age = $age;
        $this->isRound = $isRound;
    }

    public function envelope(): Envelope
    {
        $kind = $this->isRound ? 'runden' : 'halbrunden';

        return new Envelope(
            subject: sprintf(
                '%s wird %d Jahre (%s Geburtstag)',
                $this->member->full_name,
                $this->age,
                $kind
            )
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.birthday-reminder'
        );
    }
}
