<?php

use App\Models\Setting;
use App\Services\ClubBranding;
use App\Services\PdfLetterheadService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

beforeEach(function () {
    Storage::fake('branding');
});

function pdfPageCount(string $pdfContent): int
{
    return (new Fpdi)->setSourceFile(StreamReader::createByString($pdfContent));
}

test('a fresh installation starts without club data or branding', function () {
    $setting = Setting::current();

    expect($setting->name)->toBe('VEMA')
        ->and($setting->letterDateLine())->toBe('am '.now()->format('d.m.Y'));

    $branding = app(ClubBranding::class);

    expect($branding->logoPath())->toBeNull()
        ->and($branding->letterheadPath())->toBeNull()
        ->and($branding->signatories())->toBe([]);
});

test('the letter date line uses the letter place and falls back to the city', function () {
    Setting::current()->update(['city' => 'Musterdorf']);

    expect(Setting::current()->letterDateLine())->toBe('Musterdorf, am '.now()->format('d.m.Y'));

    Setting::current()->update(['letter_place' => 'Innsbruck']);

    expect(Setting::current()->letterDateLine())->toBe('Innsbruck, am '.now()->format('d.m.Y'));
});

test('the logo route serves the uploaded logo and 404s without one', function () {
    $this->get(route('branding.logo'))->assertNotFound();

    app(ClubBranding::class)->store('logo', UploadedFile::fake()->image('vereinslogo.png'));

    expect(Setting::current()->logo_path)->toBe('logo.png');
    Storage::disk('branding')->assertExists('logo.png');

    $this->get(route('branding.logo'))->assertOk();
});

test('the login page shows the club name without a logo and the logo once uploaded', function () {
    Setting::current()->update(['name' => 'Trachtenverein Musterdorf']);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Trachtenverein Musterdorf')
        ->assertDontSee('branding/logo');

    app(ClubBranding::class)->store('logo', UploadedFile::fake()->image('logo.jpg'));

    $this->get(route('login'))->assertSee('branding/logo');
});

test('replacing a file removes the previous one', function () {
    $branding = app(ClubBranding::class);

    $branding->store('signature_1', UploadedFile::fake()->image('alt.png'));
    $branding->store('signature_1', UploadedFile::fake()->image('neu.jpg'));

    Storage::disk('branding')->assertMissing('signature-1.png');
    Storage::disk('branding')->assertExists('signature-1.jpg');

    $branding->delete('signature_1');

    Storage::disk('branding')->assertMissing('signature-1.jpg');
    expect(Setting::current()->signatory_1_image_path)->toBeNull();
});

test('only configured signatories are printed on letters', function () {
    Setting::current()->update([
        'signatory_1_title' => 'Der Obmann',
        'signatory_1_name' => 'MUSTER Max',
    ]);

    expect(app(ClubBranding::class)->signatories())->toBe([
        ['title' => 'Der Obmann', 'name' => 'MUSTER Max', 'imagePath' => null],
    ]);

    $html = view('pdf.partials.signatures')->render();

    expect($html)->toContain('Der Obmann')
        ->toContain('(MUSTER Max)')
        ->not->toContain('<img');
});

test('PDFs stay unchanged without a letterhead', function () {
    $content = Pdf::loadHTML('<p>Rundschreiben</p>')->output();

    expect(app(PdfLetterheadService::class)->applyClubLetterhead($content))->toBe($content);
});

test('the letterhead is laid under every page of the PDF', function () {
    $letterhead = Pdf::loadHTML('<p>Briefpapier</p>')->output();

    app(ClubBranding::class)->store(
        'letterhead',
        UploadedFile::fake()->createWithContent('briefpapier.pdf', $letterhead)
    );

    $content = Pdf::loadHTML('<p>Seite 1</p><div style="page-break-before: always;">Seite 2</div>')->output();

    $result = app(PdfLetterheadService::class)->applyClubLetterhead($content);

    expect($result)->not->toBe($content)
        ->and(pdfPageCount($result))->toBe(2);
});
