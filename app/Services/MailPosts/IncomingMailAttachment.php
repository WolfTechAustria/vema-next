<?php

namespace App\Services\MailPosts;

final readonly class IncomingMailAttachment
{
    public function __construct(
        public string $name,
        public string $mimeType,
        public string $content,
        public bool $inline = false,
    ) {}
}
