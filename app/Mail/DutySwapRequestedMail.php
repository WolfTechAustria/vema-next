<?php

namespace App\Mail;

use App\Models\DutySwapRequest;
use App\Models\Member;
use App\Services\DutySwaps;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Neue Tausch- bzw. Abgabeanfrage an das angefragte Mitglied (bzw. an alle
 * im Dienstplan).
 */
class DutySwapRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DutySwapRequest $swap, public Member $recipient) {}

    public function envelope(): Envelope
    {
        $subject = $this->swap->isSwap()
            ? 'Tauschanfrage für deinen Dienst'
            : 'Dienst zur Übernahme angeboten';

        return new Envelope(subject: $subject.' – '.$this->swap->assignment->event->duty_date->format('d.m.Y'));
    }

    public function content(): Content
    {
        $swaps = app(DutySwaps::class);

        return new Content(
            view: 'emails.duty-swap.requested',
            with: [
                'offered' => $swaps->describe($this->swap->assignment->event),
                'wanted' => $this->swap->isSwap() ? $swaps->describe($this->swap->targetAssignment?->event) : null,
                'warnings' => $swaps->warningsForRequest($this->swap, $this->recipient),
                'portalUrl' => route('member.duties'),
            ]
        );
    }
}
