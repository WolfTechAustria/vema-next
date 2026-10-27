<?php

namespace App\Mail;

use App\Mail\Contracts\ExemptFromMailQuota;
use App\Models\Post;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Bestätigung an den Absender: Der per E-Mail eingereichte Beitrag liegt
 * als Entwurf in VEMA.
 */
class MailPostReceivedMail extends Mailable implements ExemptFromMailQuota
{
    use Queueable, SerializesModels;

    public function __construct(public Post $post) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Beitrag eingegangen: '.$this->post->title
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mail-post-received',
            with: [
                'heading' => 'Ihr Beitrag ist eingegangen',
                'clubName' => Setting::current()->name,
                'editUrl' => route('homepage-admin.posts.edit', $this->post),
                'imageCount' => $this->post->images->count(),
                'attachmentCount' => $this->post->attachments->count(),
            ]
        );
    }
}
