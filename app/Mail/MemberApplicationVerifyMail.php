<?php

namespace App\Mail;

use App\Mail\Contracts\ExemptFromMailQuota;
use App\Models\MemberApplication;
use App\Models\Setting;
use App\Services\MemberApplications;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Online-Mitgliedsanmeldung: Bestätigungslink an den Antragsteller (E-Mail-Bestätigung).
 */
class MemberApplicationVerifyMail extends Mailable implements ExemptFromMailQuota
{
    use Queueable, SerializesModels;

    public function __construct(public MemberApplication $application, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Bitte bestätige deine Anmeldung');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.member-application.verify',
            with: [
                'clubName' => Setting::current()->name,
                'verifyUrl' => route('application.verify', ['token' => $this->token]),
                'hours' => MemberApplications::VERIFY_HOURS,
            ]
        );
    }
}
