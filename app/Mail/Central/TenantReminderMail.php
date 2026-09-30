<?php

namespace App\Mail\Central;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Erinnerung an Testende bzw. Lizenzablauf (Stufen siehe tenants:send-reminders).
 */
class TenantReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $reminder,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: match ($this->reminder) {
                'trial-7' => 'Eure VEMA-Testphase endet in einer Woche',
                'trial-1' => 'Eure VEMA-Testphase endet morgen',
                'trial-ended' => 'Eure VEMA-Testphase ist beendet',
                'license-30' => 'Eure VEMA-Lizenz läuft in einem Monat ab',
                default => 'Eure VEMA-Lizenz läuft bald ab',
            }.' — '.$this->tenant->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.central.tenant-reminder',
        );
    }
}
