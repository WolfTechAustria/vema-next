<?php

use App\Models\HomepageSetting;
use App\Models\Setting;
use App\Services\ClubFavicon;
use App\Services\HomepageHosts;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('branding');
});

function storeClubLogo(int $width = 300, int $height = 120): void
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 20, 120, 60));

    ob_start();
    imagepng($image);
    Storage::disk('branding')->put('logo.png', (string) ob_get_clean());

    Setting::current()->update(['logo_path' => 'logo.png']);
}

it('falls back to the vema icon without a club logo', function () {
    $response = $this->get('/favicon.ico')->assertOk();

    expect($response->baseResponse->getFile()->getPathname())->toBe(public_path(ClubFavicon::FALLBACK_ICO));

    $this->get('/branding/icon-192.png')->assertNotFound();
});

it('turns the club logo into a favicon.ico with three sizes', function () {
    storeClubLogo();

    $response = $this->get('/favicon.ico')->assertOk()->assertHeader('Content-Type', 'image/x-icon');
    $ico = file_get_contents($response->baseResponse->getFile()->getPathname());

    expect(unpack('vreserved/vtype/vcount', substr($ico, 0, 6)))->toBe(['reserved' => 0, 'type' => 1, 'count' => 3])
        ->and(ord($ico[6]))->toBe(16);
});

it('creates square png icons for phones', function (int $size) {
    storeClubLogo();

    $response = $this->get('/branding/icon-'.$size.'.png')->assertOk();

    expect(getimagesize($response->baseResponse->getFile()->getPathname()))->toMatchArray([0 => $size, 1 => $size]);
})->with([32, 180, 192]);

it('rejects other icon sizes', function () {
    storeClubLogo();

    $this->get('/branding/icon-64.png')->assertNotFound();
});

it('creates new icons for a new logo', function () {
    storeClubLogo();
    $favicon = app(ClubFavicon::class);
    $firstIco = $favicon->icoPath();

    touch(Storage::disk('branding')->path('logo.png'), time() + 60);
    clearstatcache();

    expect($favicon->icoPath())->not->toBe($firstIco)
        ->and(file_exists($firstIco))->toBeFalse();
});

it('links the icons on the login page and on the homepage', function () {
    storeClubLogo();
    HomepageSetting::current()->update(['enabled' => true, 'domain' => 'mv-angerberg.at']);
    app(HomepageHosts::class)->forgetSingleDomain();

    $this->get('/login')
        ->assertSee('/favicon.ico?v=', false)
        ->assertSee('rel="apple-touch-icon"', false);

    $this->get('http://mv-angerberg.at/')
        ->assertSee('href="http://mv-angerberg.at/favicon.ico?v=', false)
        ->assertSee('href="http://mv-angerberg.at/icon-180.png?v=', false);

    $this->get('http://mv-angerberg.at/favicon.ico')->assertOk()->assertHeader('Content-Type', 'image/x-icon');
});
