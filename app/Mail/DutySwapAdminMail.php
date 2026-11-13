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
 * Hinweis an den Verein: Dienst wurde getauscht bzw. ein Tausch wartet auf
 * die Freigabe.
 */
class DutySwapAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DutySwapRequest $swap) {}

    public function envelope(): Envelope
    {
        $subject = $this->swap->status === DutySwapStatus::AwaitingApproval
            ? 'Diensttausch wartet auf Freigabe'
            : ($this->swap->isSwap() ? 'Dienste getauscht' : 'Dienst übergeben');

        return new Envelope(subject: $subject.': '.$this->swap->requester->full_name.' ↔ '.$this->swap->acceptedBy?->full_name);
    }

    public function content(): Content
    {
        $swaps = app(DutySwaps::class);

        return new Content(
            view: 'emails.duty-swap.admin',
            with: [
                'offered' => $swaps->describe($this->swap->assignment->event),
                'wanted' => $this->swap->isSwap() ? $swaps->describe($this->swap->targetAssignment?->event) : null,
                'awaitingApproval' => $this->swap->status === DutySwapStatus::AwaitingApproval,
                'swapsUrl' => route('duty-plan.swaps'),
            ]
        );
    }
}
