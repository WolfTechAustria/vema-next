<?php

use App\Models\User;
use App\Services\UserManual;

it('renders the user manual from the markdown file', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('user-manual'))
        ->assertOk()
        ->assertSee('VEMA – Benutzerhandbuch')
        ->assertSee('5. Mitglieder')
        ->assertSee('5.2 Neues Mitglied anlegen')
        ->assertSee(route('user-manual.image', '05-mitglieder-liste.png', false), false);
});

it('links the user manual in the staff sidebar', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('user-manual'), false);
});

it('requires a login for the user manual and its images', function () {
    $this->get(route('user-manual'))->assertRedirect(route('login'));
    $this->get(route('user-manual.image', '01-login.png'))->assertRedirect(route('login'));
});

it('serves the manual screenshots', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('user-manual.image', '01-login.png'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('returns 404 for unknown manual screenshots', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('user-manual.image', 'gibt-es-nicht.png'))
        ->assertNotFound();
});

it('every screenshot referenced in the manual exists', function () {
    $manual = app(UserManual::class);

    preg_match_all('/\]\(bilder\/([^)]+)\)/', file_get_contents($manual->markdownPath()), $matches);

    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $image) {
        expect(is_file($manual->imagePath($image)))->toBeTrue("Bild fehlt: {$image}");
    }
});

it('gives repeated headings unique anchors', function () {
    $contents = app(UserManual::class)->parse("# H\n\n## 1. A\n\n### Tipps\n\nx\n\n## 2. B\n\n### Tipps\n\ny\n");

    expect($contents['chapters'][0]['sections'][0]['id'])->toBe('tipps')
        ->and($contents['chapters'][1]['sections'][0]['id'])->toBe('tipps-2');
});

it('turns figure placeholders into placeholder boxes', function () {
    $contents = app(UserManual::class)->parse("# H\n\n## 1. A\n\n*[Abbildung folgt: Termine]*\n");

    expect($contents['chapters'][0]['html'])
        ->toContain('<div class="manual-figure-placeholder">Abbildung folgt: Termine</div>');
});

it('splits the markdown into chapters, sections, figures and notes', function () {
    $contents = app(UserManual::class)->parse(<<<'MD'
        # Handbuch

        ## Inhalt

        1. [Start](#1-start)

        > **Hinweis:** Demodaten.

        ## 1. Start

        Einleitung.

        ### 1.1 Erster Schritt

        ![Ein Bild](bilder/01-login.png)

        > **Achtung:** Vorsicht.

        <script>alert(1)</script>
        MD);

    expect($contents['title'])->toBe('Handbuch')
        ->and($contents['notes'])->toHaveCount(1)
        ->and($contents['chapters'])->toHaveCount(1);

    $chapter = $contents['chapters'][0];
    $section = $chapter['sections'][0];

    expect($chapter['title'])->toBe('1. Start')
        ->and($chapter['text'])->toContain('einleitung')
        ->and($section['id'])->toBe('11-erster-schritt')
        ->and($section['html'])->toContain('<figure class="manual-figure">')
        ->and($section['html'])->toContain('<figcaption>Ein Bild</figcaption>')
        ->and($section['html'])->toContain('manual-note manual-note-warning')
        ->and($section['html'])->not->toContain('<script>');
});
