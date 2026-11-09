<?php

namespace App\Services;

use App\Enums\Plan;
use App\Models\PlatformNews;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Übernimmt Neuigkeiten, die mit einem Update ausgeliefert werden
 * (resources/news/*.md), als Entwürfe in den Plattform-Admin. Jede Datei
 * wird genau einmal übernommen — auch wenn der Entwurf später gelöscht wird.
 *
 * Aufbau einer Datei (Name z. B. 2026-11-09-online-anmeldung.md):
 *
 *     ---
 *     title: Online-Anmeldung für neue Mitglieder
 *     min_plan: verein        (optional, Paket-Wert)
 *     ---
 *     Text in Markdown …
 */
class PlatformNewsDrafts
{
    public function __construct(private ?string $directory = null) {}

    public function directory(): string
    {
        return $this->directory ?? resource_path('news');
    }

    /**
     * Legt für neue Dateien Entwürfe an; liefert die Anzahl neuer Entwürfe.
     */
    public function sync(): int
    {
        if (! File::isDirectory($this->directory())) {
            return 0;
        }

        $imported = DB::connection('landlord')->table('platform_news_sources')->pluck('key')->all();
        $created = 0;

        foreach (File::files($this->directory()) as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $key = $file->getFilenameWithoutExtension();

            if (in_array($key, $imported, true)) {
                continue;
            }

            $draft = $this->parse((string) File::get($file->getPathname()));

            if ($draft === null) {
                report(new \RuntimeException('Neuigkeit '.$file->getFilename().' hat keinen gültigen Kopf (title).'));

                continue;
            }

            DB::connection('landlord')->transaction(function () use ($draft, $key) {
                $news = PlatformNews::create($draft);

                DB::connection('landlord')->table('platform_news_sources')->insert([
                    'key' => $key,
                    'platform_news_id' => $news->id,
                    'imported_at' => now(),
                ]);
            });

            $created++;
        }

        return $created;
    }

    /**
     * @return array{title: string, body: string, min_plan: ?string}|null
     */
    public function parse(string $content): ?array
    {
        $content = str_replace("\r\n", "\n", $content);

        if (! preg_match('/^---\n(.*?)\n---\n(.*)$/s', $content, $matches)) {
            return null;
        }

        $meta = [];

        foreach (explode("\n", $matches[1]) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $meta[trim($name)] = trim($value);
            }
        }

        $title = mb_substr($meta['title'] ?? '', 0, 150);
        $body = trim($matches[2]);

        if ($title === '' || $body === '') {
            return null;
        }

        return [
            'title' => $title,
            'body' => $body,
            'min_plan' => Plan::tryFrom($meta['min_plan'] ?? '')?->value,
        ];
    }
}
