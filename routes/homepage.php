<?php

use App\Http\Controllers\HomepageController;
use App\Http\Controllers\HomepageMediaController;
use App\Services\HomepageHosts;
use Illuminate\Support\Facades\Route;

/*
 * Öffentliche Vereinshomepage. Auf der eigenen Domain des Vereins leitet
 * ServeHomepageHost jede Anfrage intern hierher um (ohne /site in der
 * Adresse); auf der App-Domain dient /site als Vorschau.
 */
Route::prefix(HomepageHosts::PATH_PREFIX)
    ->name('homepage.')
    ->middleware('homepage.published')
    ->group(function () {
        Route::get('/', [HomepageController::class, 'home'])
            ->name('home');

        Route::get('/news', [HomepageController::class, 'posts'])
            ->name('posts.index');

        Route::get('/news/{slug}', [HomepageController::class, 'post'])
            ->name('posts.show');

        Route::get('/termine', [HomepageController::class, 'events'])
            ->name('events');

        Route::get('/ueber-uns', [HomepageController::class, 'about'])
            ->name('about');

        Route::get('/vorstand', [HomepageController::class, 'board'])
            ->name('board');

        Route::get('/kontakt', [HomepageController::class, 'contact'])
            ->name('contact');

        Route::get('/bilder/{image}/{size}', [HomepageMediaController::class, 'postImage'])
            ->whereNumber('image')
            ->whereIn('size', ['gross', 'klein'])
            ->name('images.show');

        Route::get('/titelbild', [HomepageMediaController::class, 'hero'])
            ->name('hero');

        Route::get('/hinweis/bild', [HomepageMediaController::class, 'popupImage'])
            ->name('popup-image');

        Route::get('/logo', [HomepageMediaController::class, 'logo'])
            ->name('logo');

        Route::get('/downloads/{attachment}', [HomepageMediaController::class, 'attachment'])
            ->whereNumber('attachment')
            ->name('attachments.show');

        Route::get('/ankuendigung/{kind}', [HomepageMediaController::class, 'announcement'])
            ->whereIn('kind', ['bild', 'datei'])
            ->name('announcement');

        Route::get('/abschnitt/{section}/{kind}', [HomepageMediaController::class, 'section'])
            ->whereNumber('section')
            ->whereIn('kind', ['bild', 'bild2', 'datei'])
            ->name('sections.media');

        Route::get('/vorstandsfoto/{member}', [HomepageMediaController::class, 'boardPhoto'])
            ->whereNumber('member')
            ->name('board.photo');

        Route::get('/{slug}', [HomepageController::class, 'page'])
            ->where('slug', '[a-z0-9-]+')
            ->name('page');
    });
