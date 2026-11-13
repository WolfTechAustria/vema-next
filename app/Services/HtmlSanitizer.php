<?php

namespace App\Services;

use App\Services\HtmlSanitizer\HomepageAttributeSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonyHtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Bereinigt HTML aus dem Homepage-Editor, bevor es öffentlich auf der
 * Vereinshomepage erscheint. Erlaubt sind Textformatierung, Listen, Links,
 * Tabellen, Bilder, Einbettungen von YouTube/Vimeo/Google Maps und
 * unkritische Inline-Styles — Skripte, Event-Handler, Formulare und fremde
 * Einbettungen werden entfernt.
 */
class HtmlSanitizer
{
    /**
     * Erlaubte Elemente mit ihren erlaubten Attributen.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_ELEMENTS = [
        'p' => [],
        'br' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'h5' => [],
        'h6' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'small' => [],
        'sub' => [],
        'sup' => [],
        'code' => [],
        'pre' => [],
        'a' => ['href', 'title'],
        'ul' => [],
        'ol' => [],
        'li' => ['data-list'],
        'blockquote' => [],
        'span' => [],
        'div' => [],
        'section' => [],
        'figure' => [],
        'figcaption' => [],
        'hr' => [],
        'table' => [],
        'caption' => [],
        'colgroup' => ['span'],
        'col' => ['span'],
        'thead' => [],
        'tbody' => [],
        'tfoot' => [],
        'tr' => [],
        'th' => ['colspan', 'rowspan', 'scope'],
        'td' => ['colspan', 'rowspan'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'iframe' => ['src', 'width', 'height', 'title', 'allow', 'allowfullscreen', 'loading', 'referrerpolicy'],
    ];

    /**
     * Auf allen erlaubten Elementen zulässig; den Inhalt prüft der
     * HomepageAttributeSanitizer.
     *
     * @var list<string>
     */
    private const GLOBAL_ATTRIBUTES = ['class', 'style', 'id'];

    private ?SymfonyHtmlSanitizer $sanitizer = null;

    public function sanitize(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $clean = trim($this->withoutEmptyMedia($this->sanitizer()->sanitize($html)));

        // Quill speichert einen leeren Editor als "<p><br></p>".
        return in_array($clean, ['<p><br /></p>', '<p></p>'], true) ? '' : $clean;
    }

    /**
     * Bilder und Einbettungen, deren Quelle verworfen wurde, ganz entfernen.
     */
    private function withoutEmptyMedia(string $html): string
    {
        return (string) preg_replace(
            ['/<img(?![^>]*\ssrc=)[^>]*\/?>/i', '/<iframe(?![^>]*\ssrc=)[^>]*>\s*<\/iframe>/i'],
            '',
            $html
        );
    }

    private function sanitizer(): SymfonyHtmlSanitizer
    {
        if ($this->sanitizer !== null) {
            return $this->sanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['https'])
            ->allowRelativeMedias()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withAttributeSanitizer(new HomepageAttributeSanitizer)
            ->withMaxInputLength(500_000);

        foreach (self::ALLOWED_ELEMENTS as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        foreach (self::GLOBAL_ATTRIBUTES as $attribute) {
            $config = $config->allowAttribute($attribute, '*');
        }

        return $this->sanitizer = new SymfonyHtmlSanitizer($config);
    }
}
