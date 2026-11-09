<?php

namespace App\Mail;

use App\Mail\Contracts\ExemptFromMailQuota;
use App\Models\MemberApplication;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Online-Mitgliedsanmeldung: Eingangsbestätigung an den Antragsteller.
 */
class MemberApplicationConfirmationMail extends Mailable implements ExemptFromMailQuota
{
    use Queueable, SerializesModels;

    public function __construct(public MemberApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Deine Anmeldung ist eingegangen');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.member-application.confirmation',
            with: [
                'clubName' => Setting::current()->name,
            ]
        );
    }
}
