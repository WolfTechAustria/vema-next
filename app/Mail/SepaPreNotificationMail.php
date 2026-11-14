<?php

namespace App\Mail;

use App\Models\SepaDebitItem;
use App\Models\SepaDebitRun;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Vorabinformation (Pre-Notification) vor einer SEPA-Lastschrift: Betrag,
 * Einzugsdatum, Mandatsreferenz und Gläubiger-ID.
 */
class SepaPreNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SepaDebitRun $run, public SepaDebitItem $item) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Ankündigung SEPA-Lastschrift: Mitgliedsbeitrag am '.$this->run->collection_date->format('d.m.Y'));
    }

    public function content(): Content
    {
        $setting = Setting::current();

        return new Content(
            view: 'emails.sepa.pre-notification',
            with: [
                'clubName' => $setting->name,
                'creditorId' => $setting->sepa_creditor_id,
                'member' => $this->item->entry?->member,
                'yearName' => $this->run->year?->name ?: $this->run->year?->year,
            ]
        );
    }
}
