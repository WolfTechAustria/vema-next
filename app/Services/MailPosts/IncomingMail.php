<?php

namespace App\Services\MailPosts;

/**
 * Eingegangene E-Mail, unabhängig vom Abruf (IMAP oder Test-Fake).
 */
final readonly class IncomingMail
{
    /**
     * @param  list<IncomingMailAttachment>  $attachments
     * @param  mixed  $handle  Verweis auf die Nachricht beim Postfach (z. B. IMAP-Message)
     */
    public function __construct(
        public string $messageId,
        public string $fromAddress,
        public ?string $fromName,
        public string $subject,
        public ?string $html,
        public ?string $text,
        public array $attachments = [],
        public mixed $handle = null,
    ) {}
}
