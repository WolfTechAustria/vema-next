<?php

namespace App\Mail;

use App\Models\DutyPlanAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DutyReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public DutyPlanAssignment $assignment;

    public function __construct(DutyPlanAssignment $assignment)
    {
        $this->assignment = $assignment;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Erinnerung: Dienst am '
            . $this->assignment->event->duty_date->format('d.m.Y')
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.duty-reminder');
    }
}
