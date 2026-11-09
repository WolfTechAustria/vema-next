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
 * Online-Mitgliedsanmeldung: Hinweis an den Verein: neue Anmeldung wartet auf Freigabe.
 */
class MemberApplicationReceivedMail extends Mailable implements ExemptFromMailQuota
{
    use Queueable, SerializesModels;

    public function __construct(public MemberApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Neue Online-Anmeldung: '.$this->application->full_name);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.member-application.received',
            with: [
                'clubName' => Setting::current()->name,
                'applicationUrl' => route('members.applications.show', $this->application),
            ]
        );
    }
}
