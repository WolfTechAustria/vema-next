<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Liest das Benutzerhandbuch aus resources/docs/anleitung/anleitung.md und
 * zerlegt es in Kapitel (##) und Abschnitte (###). Die Seite /anleitung baut
 * sich daraus bei jeder Änderung an der Markdown-Datei automatisch neu auf.
 */
class UserManual
{
    public function markdownPath(): string
    {
        return resource_path('docs/anleitung/anleitung.md');
    }

    public function imagePath(string $image): string
    {
        return resource_path('docs/anleitung/bilder/'.$image);
    }

    /**
     * @return array{
     *     title: string,
     *     notes: list<string>,
     *     chapters: list<array{
     *         id: string,
     *         title: string,
     *         html: string,
     *         text: string,
     *         sections: list<array{id: string, title: string, html: string, text: string}>
     *     }>
     * }
     */
    public function contents(): array
    {
        $path = $this->markdownPath();

        return Cache::remember(
            // Neu aufbauen, sobald sich das Markdown oder dieser Parser ändert.
            'user-manual:'.md5($path.filemtime($path).filemtime(__FILE__)),
            now()->addDay(),
            fn () => $this->parse((string) file_get_contents($path))
        );
    }

    /**
     * @return array{title: string, notes: list<string>, chapters: list<array<string, mixed>>}
     */
    public function parse(string $markdown): array
    {
        $markdown = str_replace("\r\n", "\n", $markdown);

        preg_match('/^# (.+)$/m', $markdown, $titleMatch);

        $parts = preg_split('/^## /m', $markdown);
        array_shift($parts);

        $notes = [];
        $chapters = [];
        $usedIds = [];

        foreach ($parts as $part) {
            [$heading, $body] = array_pad(explode("\n", $part, 2), 2, '');
            $heading = trim($heading);

            // Das Inhaltsverzeichnis im Markdown ersetzt die Seite durch ihr eigenes.
            if ($heading === 'Inhalt') {
                preg_match_all('/^> .+$/m', $body, $noteMatches);
                $notes = array_map(fn (string $note) => $this->render($note), $noteMatches[0]);

                continue;
            }

            $sectionParts = preg_split('/^### /m', $body);
            $introMarkdown = array_shift($sectionParts);

            $sections = [];

            foreach ($sectionParts as $sectionPart) {
                [$sectionHeading, $sectionBody] = array_pad(explode("\n", $sectionPart, 2), 2, '');
                $html = $this->render($sectionBody);

                $sections[] = [
                    'id' => $this->uniqueId($sectionHeading, $usedIds),
                    'title' => trim($sectionHeading),
                    'html' => $html,
                    'text' => $this->searchText($sectionHeading.' '.$html),
                ];
            }

            $introHtml = $this->render($introMarkdown);

            $chapters[] = [
                'id' => $this->uniqueId($heading, $usedIds),
                'title' => $heading,
                'html' => $introHtml,
                'text' => $this->searchText($heading.' '.$introHtml),
                'sections' => $sections,
            ];
        }

        return [
            'title' => trim($titleMatch[1] ?? 'Anleitung'),
            'notes' => $notes,
            'chapters' => $chapters,
        ];
    }

    /**
     * @param  array<string, true>  $usedIds
     */
    private function uniqueId(string $heading, array &$usedIds): string
    {
        $base = Str::slug($heading) ?: 'abschnitt';
        $id = $base;

        for ($suffix = 2; isset($usedIds[$id]); $suffix++) {
            $id = $base.'-'.$suffix;
        }

        $usedIds[$id] = true;

        return $id;
    }

    private function render(string $markdown): string
    {
        $markdown = preg_replace('/^-{3,}\s*$/m', '', $markdown);

        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        // Screenshots als Abbildung mit Bildunterschrift, ausgeliefert über die geschützte Bild-Route.
        $html = preg_replace_callback(
            '/<p><img src="bilder\/([A-Za-z0-9._-]+)" alt="([^"]*)" \/><\/p>/',
            function (array $match): string {
                $class = str_contains($match[1], 'mobil') ? 'manual-figure manual-figure-mobile' : 'manual-figure';
                // Relativ, weil das gecachte HTML von allen Vereinen (Domains) genutzt wird.
                $src = route('user-manual.image', $match[1], false);

                return '<figure class="'.$class.'"><img src="'.e($src).'" alt="'.$match[2].'" loading="lazy">'
                    .'<figcaption>'.$match[2].'</figcaption></figure>';
            },
            $html
        );

        // Platzhalter *[Abbildung folgt: …]* für noch fehlende Screenshots.
        $html = preg_replace(
            '/<p><em>\[Abbildung folgt: (.*?)\]<\/em><\/p>/',
            '<div class="manual-figure-placeholder">Abbildung folgt: $1</div>',
            $html
        );

        // Hinweise: "Achtung"/"Wichtig" als Warnung, alles andere als Tipp.
        return preg_replace_callback(
            '/<blockquote>(.*?)<\/blockquote>/s',
            function (array $match): string {
                $isWarning = preg_match('/Achtung|Wichtig/', $match[1]) === 1;

                return '<blockquote class="manual-note'.($isWarning ? ' manual-note-warning' : '').'">'.$match[1].'</blockquote>';
            },
            $html
        );
    }

    private function searchText(string $html): string
    {
        return Str::lower(trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html)))));
    }
}
