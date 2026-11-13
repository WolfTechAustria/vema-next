<?php

namespace App\Mail;

use App\Enums\DutySwapStatus;
use App\Models\DutySwapRequest;
use App\Services\DutySwaps;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Rückmeldung zu einer Anfrage: angenommen, abgelehnt, wartet auf Freigabe,
 * freigegeben oder vom Verein abgelehnt. Geht an das anfragende Mitglied,
 * bei der Entscheidung des Vereins auch an das übernehmende.
 */
class DutySwapAnsweredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DutySwapRequest $swap, public bool $forAcceptor = false) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->swap->status) {
            DutySwapStatus::Completed => $this->swap->isSwap() ? 'Diensttausch erledigt' : 'Dienst übergeben',
            DutySwapStatus::AwaitingApproval => 'Diensttausch angenommen – wartet auf Freigabe',
            DutySwapStatus::Declined => 'Diensttausch abgelehnt',
            DutySwapStatus::Rejected => 'Diensttausch nicht freigegeben',
            default => 'Diensttausch',
        };

        return new Envelope(subject: $subject.' – '.$this->swap->assignment->event->duty_date->format('d.m.Y'));
    }

    public function content(): Content
    {
        $swaps = app(DutySwaps::class);

        return new Content(
            view: 'emails.duty-swap.answered',
            with: [
                'offered' => $swaps->describe($this->swap->assignment->event),
                'wanted' => $this->swap->isSwap() ? $swaps->describe($this->swap->targetAssignment?->event) : null,
                'portalUrl' => route('member.duties'),
            ]
        );
    }
}
