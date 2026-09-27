<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Plattform-Routen (nur TENANCY_MODE=multi)
|--------------------------------------------------------------------------
|
| Nur auf der Plattform-Domain (z. B. vemat.at) erreichbar. Namen beginnen
| mit "central." — IdentifyTenant lässt auf der Plattform-Domain nur diese
| zu. Im Modus "single" wird hier nichts registriert.
|
*/

if (config('tenancy.mode') !== 'multi') {
    return;
}

Route::domain(config('tenancy.central_domain'))->group(function () {
    Route::view('/', 'central.home')->name('central.home');
});
