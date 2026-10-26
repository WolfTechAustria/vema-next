<?php

namespace App\Services;

use App\Models\HomepageNotFound;
use App\Models\HomepagePage;
use App\Models\HomepageRedirect;
use App\Models\Post;
use Illuminate\Support\Str;

/**
 * Leitet alte Adressen auf die Homepage um, wenn eine Seite nicht gefunden
 * wurde: zuerst eingetragene Weiterleitungen, dann typische Adressen einer
 * früheren Vereinsseite (WordPress u. Ä.), z. B. „/archive/titel/“ auf den
 * gleichnamigen Beitrag. Was nicht zugeordnet werden kann, wird protokolliert.
 */
class HomepageRedirects
{
    /**
     * Höchstzahl protokollierter Adressen; ältere fallen heraus.
     */
    public const NOT_FOUND_LIMIT = 500;

    /**
     * Übersichts- und Archivseiten typischer Vereinsseiten → News.
     *
     * @var list<string>
     */
    private const LISTING_SEGMENTS = [
        'aktuelles', 'archive', 'archiv', 'author', 'autor', 'beitraege', 'berichte', 'blog',
        'category', 'kategorie', 'neuigkeiten', 'news', 'tag', 'thema',
    ];

    /**
     * Anhängsel von WordPress-Adressen ohne eigene Bedeutung.
     *
     * @var list<string>
     */
    private const IGNORED_TRAILING_SEGMENTS = ['feed', 'amp', 'embed', 'trackback', 'comments'];

    /**
     * Feste Seiten der Homepage nach Adresse.
     *
     * @var array<string, string>
     */
    private const FIXED_PAGES = [
        'news' => 'homepage.posts.index',
        'termine' => 'homepage.events',
        'ueber-uns' => 'homepage.about',
        'vorstand' => 'homepage.board',
        'kontakt' => 'homepage.contact',
    ];

    /**
     * Dateien und Pfade, die nur Bots abfragen — nicht protokollieren.
     */
    private const IGNORED_NOT_FOUND = '#(^/(wp-|\.well-known|cgi-bin|\.env|\.git|xmlrpc|vendor/))|\.(css|js|map|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|eot|txt|xml|json|php)$#';

    /**
     * Ziel (Homepage-Pfad oder absolute Adresse) für eine nicht gefundene
     * Adresse oder null.
     */
    public function resolve(string $path): ?string
    {
        $path = HomepageRedirect::normalizePath($path);

        if ($path === '/') {
            return null;
        }

        $redirect = $this->configuredRedirect($path);

        if ($redirect !== null) {
            $redirect->increment('hits', 1, ['last_hit_at' => now()]);

            return $redirect->target;
        }

        $target = $this->automaticTarget($path);

        // Eine ausgeblendete feste Seite darf nicht auf sich selbst zeigen.
        if ($target !== null && HomepageRedirect::normalizePath($target) === $path) {
            return null;
        }

        return $target;
    }

    public function recordNotFound(string $path): void
    {
        $path = HomepageRedirect::normalizePath($path);

        if ($path === '/' || preg_match(self::IGNORED_NOT_FOUND, $path)) {
            return;
        }

        $entry = HomepageNotFound::query()->firstOrNew(['path' => $path]);
        $isNew = ! $entry->exists;

        $entry->hits++;
        $entry->last_seen_at = now();
        $entry->save();

        if ($isNew) {
            $this->pruneNotFound();
        }
    }

    private function configuredRedirect(string $path): ?HomepageRedirect
    {
        $exact = HomepageRedirect::query()->where('source_path', $path)->first();

        if ($exact !== null) {
            return $exact;
        }

        // Längster passender Anfang gewinnt („/files/2008/*“ vor „/files/*“).
        return HomepageRedirect::query()
            ->where('source_path', 'like', '%*')
            ->get()
            ->filter(function (HomepageRedirect $redirect) use ($path): bool {
                $prefix = substr($redirect->source_path, 0, -1);

                return str_starts_with($path, $prefix) || $path === rtrim($prefix, '/');
            })
            ->sortByDesc(fn (HomepageRedirect $redirect) => strlen($redirect->source_path))
            ->first();
    }

    private function automaticTarget(string $path): ?string
    {
        $imported = Post::query()->published()->where('legacy_path', $path)->value('slug');

        if ($imported !== null) {
            return route('homepage.posts.show', $imported, false);
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/')), fn (string $segment) => $segment !== ''));

        while ($segments !== [] && in_array(end($segments), self::IGNORED_TRAILING_SEGMENTS, true)) {
            array_pop($segments);
        }

        // Seitenzahl einer Übersicht („/aktuelles/page/2“).
        $count = count($segments);

        if ($count >= 2 && $segments[$count - 2] === 'page' && ctype_digit($segments[$count - 1])) {
            array_splice($segments, -2);
        }

        if ($segments === [] || in_array($segments[0], ['index.html', 'index.htm', 'index.php', 'home.html', 'home'], true)) {
            return '/';
        }

        $byDate = $this->dateArchiveTarget($segments);

        if ($byDate !== null) {
            return $byDate;
        }

        $bySlug = $this->slugTarget(end($segments));

        if ($bySlug !== null) {
            return $bySlug;
        }

        if (in_array($segments[0], self::LISTING_SEGMENTS, true) || in_array(Str::beforeLast($segments[0], '.'), self::LISTING_SEGMENTS, true)) {
            return route('homepage.posts.index', [], false);
        }

        return null;
    }

    /**
     * Datumsarchiv („/archive/2024/02/14“) → News des Jahres.
     *
     * @param  list<string>  $segments
     */
    private function dateArchiveTarget(array $segments): ?string
    {
        foreach ($segments as $index => $segment) {
            if (! preg_match('/^(19|20)\d{2}$/', $segment)) {
                continue;
            }

            $rest = array_slice($segments, $index + 1);

            if (count($rest) <= 2 && collect($rest)->every(fn (string $part) => preg_match('/^\d{1,2}$/', $part) === 1)) {
                return route('homepage.posts.index', ['jahr' => $segment], false);
            }
        }

        return null;
    }

    /**
     * Letzter Teil der Adresse als Beitrag, eigene Seite oder feste Seite.
     */
    private function slugTarget(string $segment): ?string
    {
        $segment = (string) preg_replace('/\.(html?|php)$/', '', $segment);

        // Alte Seiten-Baukästen: „aktuelles---titel.html“.
        if (str_contains($segment, '---')) {
            $segment = Str::afterLast($segment, '---');
        }

        // Von Elementor vergebene Adressen tragen beim Import die Beitragsnummer.
        if (preg_match('/^elementor-(\d+)$/', $segment, $matches)) {
            $slug = Post::query()->published()->where('slug', 'like', '%-'.$matches[1])->value('slug');

            return $slug !== null ? route('homepage.posts.show', $slug, false) : null;
        }

        $slug = Str::slug($segment, '-', 'de');

        if ($slug === '') {
            return null;
        }

        if (Post::query()->published()->where('slug', $slug)->exists()) {
            return route('homepage.posts.show', $slug, false);
        }

        if (HomepagePage::query()->published()->where('slug', $slug)->exists()) {
            return route('homepage.page', $slug, false);
        }

        if (isset(self::FIXED_PAGES[$slug])) {
            return route(self::FIXED_PAGES[$slug], [], false);
        }

        return null;
    }

    private function pruneNotFound(): void
    {
        $keepFrom = HomepageNotFound::query()
            ->orderByDesc('last_seen_at')
            ->orderByDesc('notFoundID')
            ->skip(self::NOT_FOUND_LIMIT)
            ->value('last_seen_at');

        if ($keepFrom !== null) {
            HomepageNotFound::query()->where('last_seen_at', '<=', $keepFrom)->delete();
        }
    }
}
