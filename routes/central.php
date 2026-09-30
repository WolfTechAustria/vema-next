<?php

use App\Http\Controllers\Central\AdminLoginController;
use App\Http\Controllers\Central\RegistrationController;
use App\Livewire\Central\Admin\TenantDetail;
use App\Livewire\Central\Admin\Tenants;
use App\Livewire\Central\FindClub;
use App\Livewire\Central\Register;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Plattform-Routen (nur TENANCY_MODE=multi)
|--------------------------------------------------------------------------
|
| Nur auf dem Plattform-Host (z. B. app.vemat.at) erreichbar. Namen beginnen
| mit "central." — IdentifyTenant lässt dort nur diese zu. Im Modus "single"
| wird hier nichts registriert.
|
*/

if (config('tenancy.mode') !== 'multi') {
    return;
}

Route::domain(config('tenancy.platform_host'))->group(function () {
    Route::redirect('/', '/login')->name('central.home');

    Route::livewire('/login', FindClub::class)->name('central.find-club');

    // Link „Mitglieder-Login“ der Website: Verein finden, dann zum Mitglieder-Login.
    Route::redirect('/member/login', '/login?ziel=mitglieder')->name('central.member-login');

    Route::livewire('/registrieren', Register::class)->name('central.register');

    Route::get('/registrieren/bestaetigen/{token}', [RegistrationController::class, 'showVerification'])
        ->name('central.register.verify');

    Route::post('/registrieren/bestaetigen/{token}', [RegistrationController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('central.register.verify.store');

    // Plattform-Admin (Guard "platform").
    Route::prefix('admin')->group(function () {
        Route::middleware('guest:platform')->group(function () {
            Route::get('/login', [AdminLoginController::class, 'create'])->name('central.admin.login');
            Route::post('/login', [AdminLoginController::class, 'store'])->name('central.admin.login.store');
        });

        Route::middleware('auth:platform')->group(function () {
            Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('central.admin.logout');

            Route::livewire('/', Tenants::class)->name('central.admin.tenants');
            Route::livewire('/vereine/{tenant}', TenantDetail::class)->name('central.admin.tenants.show');
        });
    });
});
