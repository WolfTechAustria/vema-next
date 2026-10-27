<?php

use App\Enums\FederationMembershipStatus;
use App\Enums\Plan;
use App\Enums\TenantStatus;
use App\Livewire\Admin\Settings\Federations;
use App\Livewire\Central\Admin\TenantDetail;
use App\Livewire\Dashboard\Index;
use App\Livewire\Federation\Clubs;
use App\Mail\FederationInvitationMail;
use App\Models\FederationMembership;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
 * Verbandszentrale: Verband lädt Vereine ein, Vereine stimmen zu.
 */
uses(TestCase::class);

beforeEach(function () {
    bootMultiTenancyForTests();

    Mail::fake();

    $this->federation = createTenantWithDatabase('landesverband', 'Landesverband', ['plan' => Plan::Verband]);
    $this->club = createTenantWithDatabase('musikverein', 'Musikverein', [
        'plan' => Plan::Starter,
        'contact_email' => 'obfrau@musikverein.at',
    ]);
});

afterEach(function () {
    shutdownMultiTenancyForTests();
});

/**
 * Admin-Zugang des Mandanten (muss innerhalb von runFor aufgerufen werden).
 */
function federationTestAdmin(): User
{
    $admin = User::query()->first();
    $admin->assignRole(Role::findOrCreate('admin', 'web'));

    return $admin;
}

function inviteClub(Tenant $federation, Tenant $club): void
{
    app(TenantManager::class)->runFor($federation, function () use ($club) {
        Livewire::actingAs(federationTestAdmin())
            ->test(Clubs::class)
            ->set('search', 'Musik')
            ->assertSee($club->name)
            ->call('invite', $club->id)
            ->assertSee('wurde eingeladen');
    });
}

describe('invitation', function () {
    it('invites a club that accepts and later leaves', function () {
        inviteClub($this->federation, $this->club);

        $membership = FederationMembership::query()->sole();

        expect($membership->status)->toBe(FederationMembershipStatus::Invited)
            ->and($this->club->isMemberOf($this->federation))->toBeFalse();

        Mail::assertSent(FederationInvitationMail::class, fn ($mail) => $mail->hasTo('obfrau@musikverein.at'));

        app(TenantManager::class)->runFor($this->club->fresh(), function () use ($membership) {
            $admin = federationTestAdmin();

            Livewire::actingAs($admin)
                ->test(Index::class)
                ->assertSee('Landesverband')
                ->assertSee('lädt euren Verein ein');

            Livewire::actingAs($admin)
                ->test(Federations::class)
                ->assertSee('Einladung von Landesverband')
                ->call('accept', $membership->id)
                ->assertSee('Ihr seid jetzt Mitglied bei Landesverband');
        });

        expect($this->club->isMemberOf($this->federation))->toBeTrue()
            ->and($this->club->events()->pluck('action')->all())->toContain('Einladung in den Verband angenommen: Landesverband')
            ->and($this->federation->events()->pluck('action')->all())->toContain('Einladung in den Verband angenommen: Musikverein');

        app(TenantManager::class)->runFor($this->club->fresh(), function () use ($membership) {
            Livewire::actingAs(federationTestAdmin())
                ->test(Federations::class)
                ->call('leave', $membership->id);
        });

        expect($membership->fresh()->status)->toBe(FederationMembershipStatus::Left)
            ->and($this->club->isMemberOf($this->federation))->toBeFalse();

        // Nach dem Austritt kann der Verband erneut einladen.
        inviteClub($this->federation, $this->club);

        expect($membership->fresh()->status)->toBe(FederationMembershipStatus::Invited);
    });

    it('lets a club decline', function () {
        inviteClub($this->federation, $this->club);

        $membership = FederationMembership::query()->sole();

        app(TenantManager::class)->runFor($this->club->fresh(), function () use ($membership) {
            Livewire::actingAs(federationTestAdmin())
                ->test(Federations::class)
                ->call('decline', $membership->id);
        });

        expect($membership->fresh()->status)->toBe(FederationMembershipStatus::Declined);
    });

    it('does not offer clubs that are already invited, inactive or the federation itself', function () {
        createTenantWithDatabase('musikkapelle', 'Musikkapelle', ['status' => TenantStatus::Suspended]);
        inviteClub($this->federation, $this->club);

        app(TenantManager::class)->runFor($this->federation, function () {
            Livewire::actingAs(federationTestAdmin())
                ->test(Clubs::class)
                ->set('search', 'Musik')
                ->assertSee('Kein passender Verein gefunden')
                ->call('invite', $this->club->id)
                ->assertSee('Dieser Verein kann nicht eingeladen werden');
        });

        expect(FederationMembership::query()->count())->toBe(1);
    });

    it('withdraws an invitation', function () {
        inviteClub($this->federation, $this->club);

        app(TenantManager::class)->runFor($this->federation, function () {
            Livewire::actingAs(federationTestAdmin())
                ->test(Clubs::class)
                ->call('remove', FederationMembership::query()->sole()->id)
                ->assertSee('Die Einladung an Musikverein wurde zurückgezogen');
        });

        expect(FederationMembership::query()->count())->toBe(0);
    });
});

describe('access', function () {
    it('opens the federation center only for federations', function () {
        app(TenantManager::class)->runFor($this->club, function () {
            Livewire::actingAs(federationTestAdmin())
                ->test(Clubs::class)
                ->assertNotFound();
        });

        // Individuell freigeschaltet geht es auch ohne Paket Verband.
        $this->club->update(['feature_overrides' => ['federation' => true]]);

        app(TenantManager::class)->runFor($this->club->fresh(), function () {
            Livewire::actingAs(federationTestAdmin())
                ->test(Clubs::class)
                ->assertOk();
        });
    });

    it('lets only admins invite or answer', function () {
        app(TenantManager::class)->runFor($this->federation, function () {
            Livewire::actingAs(User::query()->first())
                ->test(Clubs::class)
                ->call('invite', $this->club->id)
                ->assertForbidden();
        });

        expect(FederationMembership::query()->count())->toBe(0);
    });

    it('does not let a club answer invitations of another club', function () {
        $otherClub = createTenantWithDatabase('schuetzen', 'Schützengilde');
        inviteClub($this->federation, $this->club);

        app(TenantManager::class)->runFor($otherClub, function () {
            expect(fn () => Livewire::actingAs(federationTestAdmin())
                ->test(Federations::class)
                ->call('accept', FederationMembership::query()->sole()->id))
                ->toThrow(ModelNotFoundException::class);
        });

        expect(FederationMembership::query()->sole()->status)->toBe(FederationMembershipStatus::Invited);
    });
});

it('shows and removes connections in the platform admin', function () {
    inviteClub($this->federation, $this->club);

    $admin = PlatformAdmin::create(['name' => 'Betreiber', 'email' => 'office@vemat.test', 'password' => 'ein-sehr-sicheres-passwort']);

    Livewire::actingAs($admin, 'platform')
        ->test(TenantDetail::class, ['tenant' => $this->federation])
        ->assertSee('Mitgliedsvereine')
        ->assertSee('Musikverein')
        ->call('removeFederationMembership', FederationMembership::query()->sole()->id)
        ->assertSee('Verbindung Landesverband – Musikverein gelöst');

    expect(FederationMembership::query()->count())->toBe(0)
        ->and($this->club->events()->latest('id')->first()->platform_admin_id)->toBe($admin->id);
});
