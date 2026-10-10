<?php

use Illuminate\Support\Facades\Route;

it('shows the vemat maintenance page for service unavailable responses', function () {
    Route::get('/_maintenance-test', fn () => abort(503));

    $this->get('/_maintenance-test')
        ->assertStatus(503)
        ->assertSee('Wartungsarbeiten')
        ->assertSee('Wir sind gleich wieder da.')
        ->assertSee(config('tenancy.website_url').'/impressum.html', false);
});

it('is self-contained so it works while assets are rebuilt', function () {
    $html = view('errors.503')->render();

    expect($html)->toContain('<title>Wartungsarbeiten | vemat</title>')
        ->not->toContain('/build/')
        ->not->toContain('<script');
});
