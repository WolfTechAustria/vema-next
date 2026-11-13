<?php

namespace App\Services\HtmlSanitizer;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Prüft die Attribute, die der Homepage-Editor zusätzlich erlaubt:
 * Inline-Styles (nur unkritische Eigenschaften), iframe-Quellen (nur
 * YouTube, Vimeo, Google Maps) und Anker-IDs.
 */
class HomepageAttributeSanitizer implements AttributeSanitizerInterface
{
    /**
     * Erlaubte CSS-Eigenschaften; Einträge mit "-" am Ende sind Präfixe.
     *
     * @var list<string>
     */
    private const STYLE_PROPERTIES = [
        'color', 'background', 'background-color', 'opacity',
        'text-align', 'text-decoration', 'text-transform', 'font-size', 'font-weight', 'font-style', 'line-height', 'letter-spacing', 'white-space',
        'width', 'min-width', 'max-width', 'height', 'min-height', 'max-height', 'aspect-ratio', 'object-fit', 'object-position',
        'margin', 'margin-', 'padding', 'padding-', 'border', 'border-', 'outline', 'box-shadow',
        'vertical-align', 'float', 'clear', 'display', 'overflow', 'overflow-x', 'overflow-y',
        'flex', 'flex-', 'gap', 'row-gap', 'column-gap', 'justify-content', 'justify-items', 'align-items', 'align-content', 'align-self',
        'grid-template-columns', 'grid-template-rows', 'grid-column', 'grid-row', 'list-style-type',
    ];

    /**
     * @var list<string>
     */
    private const DISPLAY_VALUES = ['block', 'inline', 'inline-block', 'flex', 'inline-flex', 'grid', 'none', 'table', 'table-cell', 'table-row'];

    /**
     * Erlaubte Einbettungen: Host => Pfad-Präfix.
     *
     * @var array<string, string>
     */
    private const IFRAME_SOURCES = [
        'www.youtube-nocookie.com' => '/embed/',
        'player.vimeo.com' => '/video/',
        'www.google.com' => '/maps/embed',
        'maps.google.com' => '/maps',
    ];

    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): ?array
    {
        return ['style', 'src', 'id'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return match ($attribute) {
            'style' => $this->sanitizeStyle($value),
            'src' => $element === 'iframe' ? $this->sanitizeIframeSource($value) : $value,
            'id' => preg_match('/^[A-Za-z][\w-]{0,63}$/', $value) === 1 ? $value : null,
            default => $value,
        };
    }

    public function sanitizeStyle(string $style): ?string
    {
        $declarations = [];

        foreach (explode(';', html_entity_decode($style)) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);

            if ($value === '' || ! $this->isAllowedProperty($property) || ! $this->isSafeValue($value)) {
                continue;
            }

            if ($property === 'display' && ! in_array(strtolower($value), self::DISPLAY_VALUES, true)) {
                continue;
            }

            $declarations[] = $property.': '.$value;
        }

        return $declarations === [] ? null : implode('; ', $declarations);
    }

    public function sanitizeIframeSource(string $source): ?string
    {
        $source = trim($source);

        if (str_starts_with($source, '//')) {
            $source = 'https:'.$source;
        }

        $parts = parse_url($source);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);

        // YouTube nur im erweiterten Datenschutzmodus einbetten.
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'youtube-nocookie.com'], true)) {
            $host = 'www.youtube-nocookie.com';
        }

        $pathPrefix = self::IFRAME_SOURCES[$host] ?? null;
        $path = $parts['path'] ?? '';

        if ($pathPrefix === null || ! str_starts_with($path, $pathPrefix)) {
            return null;
        }

        return 'https://'.$host.$path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function isAllowedProperty(string $property): bool
    {
        if (preg_match('/^[a-z-]+$/', $property) !== 1) {
            return false;
        }

        foreach (self::STYLE_PROPERTIES as $allowed) {
            if ($property === $allowed || (str_ends_with($allowed, '-') && str_starts_with($property, $allowed))) {
                return true;
            }
        }

        return false;
    }

    private function isSafeValue(string $value): bool
    {
        return preg_match('/url\s*\(|expression|javascript:|@|\\\\|<|>|\/\*|var\s*\(|attr\s*\(/i', $value) !== 1;
    }
}
