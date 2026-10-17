<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\Post;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Übernimmt die Beiträge einer bestehenden WordPress-Seite (öffentliche
 * REST-API) als News der Vereinshomepage: Text, Titelbild, Bilder aus dem
 * Beitrag (in Originalgröße) und verlinkte PDFs als Downloads.
 *
 * Elementor-Markup wird auf einfachen Text mit Absätzen, Überschriften,
 * Listen und Links reduziert. Bereits übernommene Beiträge (gleiche
 * Adresse) werden übersprungen; ein abgebrochener Beitrag bleibt Entwurf
 * und wird beim nächsten Lauf neu übernommen.
 */
class WordPressPostImporter
{
    private const PER_PAGE = 50;

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private string $baseUrl = '';

    private string $host = '';

    /**
     * @var callable(string): void|null
     */
    private $reporter = null;

    public function __construct(
        private HtmlSanitizer $sanitizer,
        private HomepageImageService $images,
        private HomepageFileService $files,
    ) {}

    /**
     * @param  callable(string): void|null  $reporter  Fortschrittsmeldungen (z. B. Konsole)
     * @return array{imported: int, skipped: int, images: int, attachments: int, failed: list<string>}
     */
    public function import(string $siteUrl, bool $dryRun = false, ?callable $reporter = null): array
    {
        $this->baseUrl = rtrim($siteUrl, '/');
        $this->host = (string) parse_url($this->baseUrl, PHP_URL_HOST);
        $this->reporter = $reporter;

        if ($this->host === '') {
            throw new RuntimeException('Bitte die vollständige Adresse der Seite angeben, z. B. https://mein-verein.at.');
        }

        $result = ['imported' => 0, 'skipped' => 0, 'images' => 0, 'attachments' => 0, 'failed' => []];

        foreach ($this->fetchPosts() as $wordPressPost) {
            $title = $this->plainText($wordPressPost['title']['rendered'] ?? '');
            $slug = $this->slugFor($wordPressPost, $title);

            $existing = Post::query()->where('slug', $slug)->first();

            // Entwurf = Übernahme wurde abgebrochen: noch einmal von vorne.
            if ($existing?->status === PostStatus::Draft && ! $dryRun) {
                $existing->deleteWithImages();
                $existing = null;
            }

            if ($existing !== null) {
                $result['skipped']++;
                $this->report('übersprungen (bereits vorhanden): '.$title);

                continue;
            }

            $content = $this->convertContent((string) ($wordPressPost['content']['rendered'] ?? ''));
            $imageUrls = $this->featuredImageUrls((int) ($wordPressPost['featured_media'] ?? 0));
            array_push($imageUrls, ...$content['images']);
            $imageUrls = array_values(array_unique($imageUrls));

            if ($dryRun) {
                $result['imported']++;
                $result['images'] += count($imageUrls);
                $result['attachments'] += count($content['pdfs']);
                $this->report(sprintf('würde übernehmen: %s (%d Bilder, %d PDFs)', $title, count($imageUrls), count($content['pdfs'])));

                continue;
            }

            $post = Post::create([
                'title' => Str::limit($title, 200, ''),
                'slug' => $slug,
                'body_html' => $content['html'] ?: null,
                // Erst nach allen Dateien veröffentlichen (siehe oben).
                'status' => PostStatus::Draft,
                'published_at' => Carbon::parse($wordPressPost['date']),
            ]);

            foreach ($imageUrls as $imageUrl) {
                try {
                    $upload = $this->download($imageUrl);
                    $this->images->storePostImage($post, $upload);
                    @unlink($upload->getRealPath());
                    $result['images']++;
                } catch (Throwable $exception) {
                    $result['failed'][] = $title.': '.$imageUrl.' ('.$exception->getMessage().')';
                }
            }

            foreach ($content['pdfs'] as $index => $pdf) {
                try {
                    $upload = $this->download($pdf['url']);

                    $post->attachments()->create([
                        'label' => Str::limit($pdf['label'], 200, ''),
                        'file_path' => $this->files->storePdf($upload, 'homepage/posts/'.$post->postID),
                        'file_size' => $upload->getSize(),
                        'sort' => $index + 1,
                    ]);
                    @unlink($upload->getRealPath());
                    $result['attachments']++;
                } catch (Throwable $exception) {
                    $result['failed'][] = $title.': '.$pdf['url'].' ('.$exception->getMessage().')';
                }
            }

            $post->update(['status' => PostStatus::Published]);

            $result['imported']++;
            $this->report(sprintf('übernommen: %s (%d Bilder, %d PDFs)', $title, $post->images()->count(), $post->attachments()->count()));
        }

        if (! $dryRun && $result['imported'] > 0) {
            ActivityLogger::log(
                'homepage.posts_imported',
                $result['imported'].' Beiträge von '.$this->host.' übernommen ('.$result['images'].' Bilder, '.$result['attachments'].' PDFs).',
                'Post'
            );
        }

        return $result;
    }

    /**
     * Wandelt den gerenderten WordPress-Inhalt um und sammelt Bilder und PDFs.
     *
     * @return array{html: string, images: list<string>, pdfs: list<array{url: string, label: string}>}
     */
    public function convertContent(string $html): array
    {
        $images = [];
        $pdfs = [];

        if (trim($html) === '') {
            return ['html' => '', 'images' => [], 'pdfs' => []];
        }

        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="wp-root">'.$html.'</div>', LIBXML_NONET);
        libxml_clear_errors();

        $xpath = new DOMXPath($document);

        // Links auf Bilder und PDFs: Medien merken, Link entfernen.
        foreach (iterator_to_array($xpath->query('//a[@href]')) as $link) {
            /** @var DOMElement $link */
            $url = $this->absoluteUrl($link->getAttribute('href'));
            $extension = $this->extension($url);

            if ($extension === 'pdf') {
                $label = $this->plainText($link->textContent) ?: pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);
                $pdfs[$this->originalUrl($url)] = ['url' => $this->originalUrl($url), 'label' => $label];
                $this->removeWithEmptyParents($link);
            } elseif (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
                $images[] = $this->originalUrl($url);
                $this->removeWithEmptyParents($link);
            }
        }

        // Eingebettete Bilder (auch lazy geladene).
        foreach (iterator_to_array($xpath->query('//img')) as $image) {
            /** @var DOMElement $image */
            $source = $image->getAttribute('data-orig-file') ?: $image->getAttribute('data-src') ?: $image->getAttribute('src');

            if ($source !== '' && ! str_starts_with($source, 'data:')) {
                $images[] = $this->originalUrl($this->absoluteUrl($source));
            }

            $this->removeWithEmptyParents($image);
        }

        foreach (iterator_to_array($xpath->query('//script|//style|//noscript|//svg|//figcaption|//button|//form|//iframe')) as $node) {
            $node->parentNode?->removeChild($node);
        }

        $root = $document->getElementById('wp-root');
        $inner = '';

        foreach ($root?->childNodes ?? [] as $child) {
            $inner .= $document->saveHTML($child);
        }

        // Überschriften auf h2/h3 bringen, Struktur-Elemente (div, section …) entpacken.
        $inner = (string) preg_replace(['#<(/?)h1\b[^>]*>#i', '#<(/?)h[4-6]\b[^>]*>#i'], ['<$1h2>', '<$1h3>'], $inner);
        $inner = strip_tags($inner, '<p><br><h2><h3><strong><b><em><i><u><a><ul><ol><li><blockquote>');
        $inner = (string) preg_replace('#<(p|h2|h3|li|blockquote)\b[^>]*>\s*(?:&nbsp;|\xC2\xA0|<br\s*/?>|\s)*</\1>#iu', '', $inner);
        $inner = (string) preg_replace('/\s+/u', ' ', $inner);

        return [
            'html' => $this->sanitizer->sanitize($inner),
            'images' => array_values(array_unique($images)),
            'pdfs' => array_values($pdfs),
        ];
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    private function fetchPosts(): iterable
    {
        $page = 1;

        do {
            $response = Http::timeout(30)->acceptJson()->get($this->baseUrl.'/wp-json/wp/v2/posts', [
                'per_page' => self::PER_PAGE,
                'page' => $page,
                '_fields' => 'id,date,slug,title,content,featured_media',
                'orderby' => 'date',
                'order' => 'asc',
            ]);

            if ($page === 1 && ! $response->successful()) {
                throw new RuntimeException('Die Beiträge konnten nicht geladen werden (HTTP '.$response->status().'). Ist das eine WordPress-Seite mit aktiver REST-API?');
            }

            $posts = $response->successful() ? (array) $response->json() : [];

            foreach ($posts as $post) {
                yield $post;
            }

            $totalPages = (int) ($response->header('X-WP-TotalPages') ?: 1);
            $page++;
        } while ($posts !== [] && $page <= $totalPages);
    }

    /**
     * @return list<string>
     */
    private function featuredImageUrls(int $mediaID): array
    {
        if ($mediaID === 0) {
            return [];
        }

        $response = Http::timeout(30)->acceptJson()->get($this->baseUrl.'/wp-json/wp/v2/media/'.$mediaID, ['_fields' => 'source_url']);
        $url = $response->successful() ? (string) $response->json('source_url') : '';

        return $url !== '' ? [$this->originalUrl($url)] : [];
    }

    /**
     * Lädt eine Datei der Vereinsseite herunter (nur vom eigenen Host).
     */
    private function download(string $url): UploadedFile
    {
        if (parse_url($url, PHP_URL_HOST) !== $this->host) {
            throw new RuntimeException('fremder Server');
        }

        $response = Http::timeout(60)->get($url);

        // Bilder: falls das Original fehlt, die verkleinerte Fassung nehmen.
        if ($response->status() === 404 && ($fallback = $this->sizedUrls[$url] ?? null)) {
            $response = Http::timeout(60)->get($fallback);
        }

        if (! $response->successful()) {
            throw new RuntimeException('HTTP '.$response->status());
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'wpimport');
        file_put_contents($temporaryPath, $response->body());

        return new UploadedFile($temporaryPath, basename((string) parse_url($url, PHP_URL_PATH)), null, null, true);
    }

    /**
     * Verkleinerte Fassungen, falls das Original nicht erreichbar ist.
     *
     * @var array<string, string>
     */
    private array $sizedUrls = [];

    /**
     * Originaldatei statt CDN-Kopie (i0.wp.com) bzw. WordPress-Vorschaugröße.
     */
    private function originalUrl(string $url): string
    {
        $url = (string) preg_replace('#^https?://i\d\.wp\.com/#i', 'https://', $url);
        $url = Str::before($url, '?');
        $url = Str::before($url, '#');

        $original = (string) preg_replace('/-\d+x\d+(?=\.[a-z0-9]+$)/i', '', $url);

        if ($original !== $url) {
            $this->sizedUrls[$original] = $url;
        }

        return $original;
    }

    private function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        if (str_starts_with($url, '/')) {
            return $this->baseUrl.$url;
        }

        return $url;
    }

    private function extension(string $url): string
    {
        return strtolower(pathinfo((string) parse_url(Str::before($url, '?'), PHP_URL_PATH), PATHINFO_EXTENSION));
    }

    /**
     * Entfernt ein Element und danach leer gewordene Eltern (z. B. Listenpunkte).
     */
    private function removeWithEmptyParents(DOMElement $element): void
    {
        $parent = $element->parentNode;
        $parent?->removeChild($element);

        while ($parent instanceof DOMElement
            && $parent->getAttribute('id') !== 'wp-root'
            && in_array(strtolower($parent->nodeName), ['li', 'ul', 'ol', 'p', 'figure', 'span', 'div', 'a'], true)
            && trim(str_replace("\xC2\xA0", ' ', $parent->textContent)) === ''
            && (new DOMXPath($parent->ownerDocument))->query('.//img', $parent)->length === 0) {
            $next = $parent->parentNode;
            $next?->removeChild($parent);
            $parent = $next;
        }
    }

    /**
     * @param  array<string, mixed>  $wordPressPost
     */
    private function slugFor(array $wordPressPost, string $title): string
    {
        $slug = (string) ($wordPressPost['slug'] ?? '');

        // Von Elementor vergebene Platzhalter-Adressen durch eine lesbare ersetzen.
        if ($slug === '' || preg_match('/^elementor-\d+$/', $slug)) {
            $slug = (Str::slug(str_replace('/', ' ', $title), '-', 'de') ?: 'beitrag').'-'.($wordPressPost['id'] ?? Str::random(4));
        }

        return Str::limit(Str::slug(urldecode($slug), '-', 'de'), 220, '');
    }

    private function plainText(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function report(string $message): void
    {
        if ($this->reporter) {
            ($this->reporter)($message);
        }
    }
}
