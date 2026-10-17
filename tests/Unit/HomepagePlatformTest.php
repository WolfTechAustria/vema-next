<?php

use App\Enums\Plan;
use App\Models\HomepageSetting;
use App\Services\HomepageHosts;
use App\Services\TenantManager;
use Tests\TestCase;

/*
 * Ohne RefreshDatabase: Modus "multi" mit eigenen SQLite-Dateien (siehe TenancyTest).
 */
uses(TestCase::class);

describe('platform', function () {
    beforeEach(function () {
        bootMultiTenancyForTests();

        $this->tenant = createTenantWithDatabase('mv-test', 'MV Test', ['plan' => Plan::Verein]);
        $this->tenant->domains()->create(['domain' => 'www.mv-test.at', 'purpose' => HomepageHosts::PURPOSE]);

        app(TenantManager::class)->runFor($this->tenant, function () {
            HomepageSetting::current()->update(['enabled' => true, 'hero_title' => 'Willkommen beim MV Test']);
        });
    });

    afterEach(function () {
        shutdownMultiTenancyForTests();
    });

    it('serves the homepage on a homepage domain of the club', function () {
        $this->get('http://www.mv-test.at/')
            ->assertOk()
            ->assertSee('Willkommen beim MV Test');

        $this->get('http://www.mv-test.at/login')->assertNotFound();
    });

    it('keeps the club app on its subdomain', function () {
        $this->get('http://mv-test.vemat.test/login')->assertOk();
        $this->get('http://mv-test.vemat.test/site')->assertOk()->assertSee('Willkommen beim MV Test');
    });

    it('hides the homepage outside the package', function () {
        $this->tenant->update(['plan' => Plan::Starter, 'trial_ends_at' => null]);

        $this->get('http://www.mv-test.at/')->assertNotFound();
    });
});

describe('domain command', function () {
    beforeEach(function () {
        bootMultiTenancyForTests();

        $this->tenant = createTenantWithDatabase('mv-cmd', 'MV Kommando', ['plan' => Plan::Verein]);
    });

    afterEach(function () {
        shutdownMultiTenancyForTests();
    });

    it('assigns and removes a homepage domain', function () {
        $this->artisan('tenants:domain', ['slug' => 'mv-cmd', 'domain' => 'www.mv-cmd.at', '--homepage' => true])->assertSuccessful();

        expect($this->tenant->domains()->sole()->only(['domain', 'purpose']))->toBe(['domain' => 'www.mv-cmd.at', 'purpose' => 'homepage'])
            ->and(app(HomepageHosts::class)->matches('www.mv-cmd.at'))->toBeTrue();

        $this->artisan('tenants:domain', ['slug' => 'mv-cmd', 'domain' => 'www.mv-cmd.at', '--remove' => true])->assertSuccessful();

        expect($this->tenant->domains()->count())->toBe(0)
            ->and(app(HomepageHosts::class)->matches('www.mv-cmd.at'))->toBeFalse();
    });
});
