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
 * Online-Mitgliedsanmeldung: Information an den Antragsteller über die Ablehnung.
 */
class MemberApplicationRejectedMail extends Mailable implements ExemptFromMailQuota
{
    use Queueable, SerializesModels;

    public function __construct(public MemberApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Deine Anmeldung');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.member-application.rejected',
            with: [
                'clubName' => Setting::current()->name,
            ]
        );
    }
}
