<?php

namespace App\Mail;

use App\Models\Circular;
use App\Models\CircularRecipient;
use App\Services\TemplateRendererService;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CircularMail extends Mailable
{
    use Queueable, SerializesModels;

    public Circular $circular;

    public CircularRecipient $recipient;

    public string $body;

    /**
     * @param  Collection<int, CircularRecipient>|null  $household  Empfänger, die diese
     *                                                              Nachricht gemeinsam erhalten (gleiche E-Mail-Adresse);
     *                                                              ohne Angabe ermittelt aus $recipient.
     */
    public function __construct(
        Circular $circular,
        CircularRecipient $recipient,
        ?Collection $household = null
    ) {
        $this->circular = $circular;
        $this->recipient = $recipient;

        $household ??= $recipient->emailHousehold();
        $household->loadMissing(['member.city', 'externalContact']);

        /*
         * Mitglieder bzw. externe Kontakte, die angesprochen werden.
         */
        $addressees = $household
            ->map(fn (CircularRecipient $householdRecipient) => $householdRecipient->addressee())
            ->filter()
            ->values();

        if ($addressees->isEmpty()) {
            throw new \RuntimeException(
                'Rundschreiben-Empfänger konnte nicht ermittelt werden.'
            );
        }

        $this->body = app(
            TemplateRendererService::class
        )->circularForRecipients(
            $this->circular->body_html ?? '',
            $addressees
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->circular->subject
                ?: $this->circular->title
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.circular'
        );
    }

    public function attachments(): array
    {
        $this->circular->loadMissing('attachments');

        return $this->circular->attachments
            ->map(function ($attachment) {
                return Attachment::fromStorageDisk(
                    'local',
                    $attachment->file_path
                )->as(
                    $attachment->file_name
                );
            })
            ->all();
    }
}
