<?php

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonyHtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Bereinigt HTML aus dem Editor (Quill), bevor es öffentlich auf der
 * Vereinshomepage erscheint: nur Textformatierung, Listen und Links bleiben
 * erhalten — Skripte, Event-Handler und eingebettete Inhalte werden entfernt.
 */
class HtmlSanitizer
{
    /**
     * Erlaubte Elemente mit ihren erlaubten Attributen.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_ELEMENTS = [
        'p' => ['class'],
        'br' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'a' => ['href', 'title'],
        'ul' => [],
        'ol' => [],
        'li' => ['data-list', 'class'],
        'blockquote' => [],
        'span' => ['class'],
    ];

    private ?SymfonyHtmlSanitizer $sanitizer = null;

    public function sanitize(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $clean = trim($this->sanitizer()->sanitize($html));

        // Quill speichert einen leeren Editor als "<p><br></p>".
        return in_array($clean, ['<p><br /></p>', '<p></p>'], true) ? '' : $clean;
    }

    private function sanitizer(): SymfonyHtmlSanitizer
    {
        if ($this->sanitizer !== null) {
            return $this->sanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(500_000);

        foreach (self::ALLOWED_ELEMENTS as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        return $this->sanitizer = new SymfonyHtmlSanitizer($config);
    }
}
