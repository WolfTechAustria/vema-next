<?php

use App\Models\ActivityLog;
use App\Models\Post;
use App\Services\WordPressPostImporter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function wordpressJpeg(): string
{
    $image = imagecreatetruecolor(40, 30);
    ob_start();
    imagejpeg($image);

    return (string) ob_get_clean();
}

function fakeWordpressSite(): void
{
    $elementorContent = <<<'HTML'
        <div class="elementor"><section class="elementor-section"><div class="elementor-widget-text-editor">
            <p>Auch heuer durften wir zur <strong>Dorfmeisterschaft</strong> einladen.</p>
            <h4 class="elementor-heading-title">Ergebnisse</h4>
            <ul class="elementor-icon-list-items">
                <li><a href="https://verein.test/wp-content/uploads/2024/10/Ergebnisliste-Einzel.pdf"><span>Ergebnisliste Einzelwertung</span></a></li>
            </ul>
        </div>
        <div class="elementor-widget-gallery"><div class="gallery">
            <a href="https://i0.wp.com/verein.test/wp-content/uploads/2024/10/Foto-1.jpeg?ssl=1"><div class="e-gallery-image"></div></a>
            <a href="https://i0.wp.com/verein.test/wp-content/uploads/2024/10/Foto-2.jpeg?ssl=1"></a>
        </div></div>
        <script>alert('x')</script><style>.x{}</style>
        </section></div>
        HTML;

    Http::fake([
        'verein.test/wp-json/wp/v2/posts*' => Http::response([
            [
                'id' => 10,
                'date' => '2024-10-03T18:30:00',
                'slug' => 'dorfmeisterschaft-2024',
                'title' => ['rendered' => 'Dorfmeisterschaft 2024 &#8211; Bericht'],
                'content' => ['rendered' => $elementorContent],
                'featured_media' => 99,
            ],
            [
                'id' => 563,
                'date' => '2023-02-24T10:00:00',
                'slug' => 'elementor-563',
                'title' => ['rendered' => 'Bezirksmeisterschaft 2022/23'],
                'content' => ['rendered' => '<p>Zweiter Teil.</p><figure><img src="https://verein.test/wp-content/uploads/2023/02/Bild-1024x768.jpg" alt=""></figure>'],
                'featured_media' => 0,
            ],
        ], 200, ['X-WP-TotalPages' => '1']),
        'verein.test/wp-json/wp/v2/media/99*' => Http::response(['source_url' => 'https://verein.test/wp-content/uploads/2024/10/Titel.jpeg']),
        'verein.test/wp-content/uploads/2023/02/Bild.jpg' => Http::response('', 404),
        'verein.test/wp-content/uploads/*.pdf' => Http::response("%PDF-1.4\n%Test\n"),
        'verein.test/wp-content/uploads/*' => Http::response(wordpressJpeg()),
    ]);
}

it('imports posts with text, original images and pdf downloads', function () {
    fakeWordpressSite();

    $this->artisan('homepage:import-wordpress', ['url' => 'https://verein.test'])
        ->expectsOutputToContain('Fertig: 2 Beiträge')
        ->assertSuccessful();

    $post = Post::query()->where('slug', 'dorfmeisterschaft-2024')->sole();

    expect($post->title)->toBe('Dorfmeisterschaft 2024 – Bericht')
        ->and($post->published_at->format('Y-m-d H:i'))->toBe('2024-10-03 18:30')
        ->and($post->isPublic())->toBeTrue()
        ->and($post->body_html)->toContain('<strong>Dorfmeisterschaft</strong>')
        ->and($post->body_html)->toContain('<h3>Ergebnisse</h3>')
        ->and($post->body_html)->not->toContain('alert')
        ->and($post->body_html)->not->toContain('Ergebnisliste Einzelwertung')
        ->and($post->images)->toHaveCount(3)
        ->and($post->attachments()->sole()->label)->toBe('Ergebnisliste Einzelwertung');

    // Titelbild zuerst, CDN-Adressen wurden auf das Original umgestellt.
    Http::assertSent(fn ($request) => $request->url() === 'https://verein.test/wp-content/uploads/2024/10/Foto-1.jpeg');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'i0.wp.com'));

    expect(ActivityLog::where('action', 'homepage.posts_imported')->exists())->toBeTrue();
});

it('replaces elementor placeholder slugs and falls back to the resized image', function () {
    fakeWordpressSite();

    app(WordPressPostImporter::class)->import('https://verein.test');

    $post = Post::query()->where('slug', 'bezirksmeisterschaft-2022-23-563')->sole();

    expect($post->body_html)->toBe('<p>Zweiter Teil.</p>')
        ->and($post->images)->toHaveCount(1);

    Http::assertSent(fn ($request) => $request->url() === 'https://verein.test/wp-content/uploads/2023/02/Bild-1024x768.jpg');
});

it('skips posts that were already imported', function () {
    fakeWordpressSite();

    $importer = app(WordPressPostImporter::class);
    $importer->import('https://verein.test');
    $second = $importer->import('https://verein.test');

    expect($second['imported'])->toBe(0)
        ->and($second['skipped'])->toBe(2)
        ->and(Post::count())->toBe(2);
});

it('imports an aborted post again', function () {
    fakeWordpressSite();
    Post::factory()->draft()->create(['slug' => 'dorfmeisterschaft-2024', 'title' => 'Halb übernommen']);

    $result = app(WordPressPostImporter::class)->import('https://verein.test');

    expect($result['imported'])->toBe(2)
        ->and(Post::query()->where('slug', 'dorfmeisterschaft-2024')->sole()->isPublic())->toBeTrue();
});

it('changes nothing in a dry run', function () {
    fakeWordpressSite();

    $this->artisan('homepage:import-wordpress', ['url' => 'https://verein.test', '--dry-run' => true])
        ->expectsOutputToContain('Testlauf: 2 Beiträge, 4 Bilder, 1 PDFs')
        ->assertSuccessful();

    expect(Post::count())->toBe(0);
});

it('fails cleanly when the site has no rest api', function () {
    Http::fake(['*' => Http::response('nope', 404)]);

    $this->artisan('homepage:import-wordpress', ['url' => 'https://kein-wordpress.test'])
        ->expectsOutputToContain('konnten nicht geladen werden')
        ->assertFailed();
});
