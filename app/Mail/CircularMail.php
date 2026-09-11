<?php

namespace App\Mail;

use App\Models\Circular;
use App\Models\CircularRecipient;
use App\Services\TemplateRendererService;
use Illuminate\Bus\Queueable;
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

    public function __construct(
        Circular $circular,
        CircularRecipient $recipient
    ) {
        $this->circular = $circular;
        $this->recipient = $recipient;

        $this->recipient->loadMissing(
            'member.city'
        );

        $this->body = app(
            TemplateRendererService::class
        )->circular(
            $this->circular->body_html ?? '',
            $this->recipient->member
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
