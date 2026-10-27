<?php

use App\Enums\FederationMembershipStatus;
use App\Enums\Plan;
use App\Enums\TenantStatus;
use App\Exports\FederationStatisticsExport;
use App\Livewire\Admin\Settings\Federations;
use App\Livewire\Central\Admin\TenantDetail;
use App\Livewire\Dashboard\Index;
use App\Livewire\Federation\Clubs;
use App\Livewire\Federation\Documents;
use App\Livewire\Federation\Inbox;
use App\Livewire\Federation\Messages;
use App\Livewire\Federation\Statistics;
use App\Mail\FederationInvitationMail;
use App\Mail\FederationMessageMail;
use App\Models\ClubStatistic;
use App\Models\FederationFile;
use App\Models\FederationMembership;
use App\Models\FederationMessage;
use App\Models\Member;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
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

describe('statistics', function () {
    beforeEach(function () {
        app(TenantManager::class)->runFor($this->club, function () {
            Member::create(['gender' => 'w', 'name' => 'Anna', 'surname' => 'Huber', 'active' => 1, 'dateOfBirth' => now()->subYears(40), 'competitionMember' => 1]);
            Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1, 'dateOfBirth' => now()->subYears(12)]);
            Member::create(['gender' => 'm', 'name' => 'Ernst', 'surname' => 'Alt', 'active' => 0, 'dateOfBirth' => now()->subYears(80)]);
        });

        inviteClub($this->federation, $this->club);

        app(TenantManager::class)->runFor($this->club->fresh(), function () {
            Livewire::actingAs(federationTestAdmin())
                ->test(Federations::class)
                ->call('accept', FederationMembership::query()->sole()->id);
        });
    });

    it('stores figures without personal data when the club accepts', function () {
        $data = ClubStatistic::query()->where('tenant_id', $this->club->id)->sole()->data;

        expect($data)->toMatchArray([
            'members' => 2,
            'male' => 1,
            'female' => 1,
            'age_under_19' => 1,
            'age_31_50' => 1,
            'age_over_70' => 0,
            'ordinary' => 1,
        ])
            ->and(json_encode($data))->not->toContain('Huber')
            ->not->toContain('Anna');

        app(TenantManager::class)->runFor($this->federation, function () {
            Livewire::actingAs(federationTestAdmin())
                ->test(Statistics::class)
                ->assertSee('Musikverein')
                ->assertSee('Stand '.now()->format('d.m.Y'))
                ->assertDontSee('Huber');

            Livewire::actingAs(federationTestAdmin())
                ->test(Index::class)
                ->assertSee('Mitgliedsvereine');
        });
    });

    it('refreshes the figures nightly and stops after leaving', function () {
        app(TenantManager::class)->runFor($this->club, function () {
            Member::create(['gender' => 'w', 'name' => 'Eva', 'surname' => 'Neu', 'active' => 1]);
        });

        $this->artisan('federation:collect-statistics')->assertSuccessful();

        expect(ClubStatistic::query()->sole()->data['members'])->toBe(3)
            ->and(ClubStatistic::query()->sole()->data['age_unknown'])->toBe(1);

        FederationMembership::query()->sole()->update(['status' => FederationMembershipStatus::Left]);

        app(TenantManager::class)->runFor($this->federation, function () {
            Livewire::actingAs(federationTestAdmin())
                ->test(Statistics::class)
                ->assertDontSee('Musikverein')
                ->assertSee('Noch keine Mitgliedsvereine');
        });
    });

    it('exports the figures to excel', function () {
        Excel::fake();

        app(TenantManager::class)->runFor($this->federation, function () {
            Livewire::actingAs(federationTestAdmin())
                ->test(Statistics::class)
                ->call('export');
        });

        Excel::assertDownloaded('mitgliedsvereine-'.now()->format('Y-m-d').'.xlsx', function (FederationStatisticsExport $export) {
            return $export->array()[0][0] === 'Musikverein' && $export->array()[0][2] === 2;
        });
    });
});

/**
 * Musikverein ist aktives Mitglied des Landesverbands.
 */
function connectClub(Tenant $federation, Tenant $club): void
{
    FederationMembership::query()->create([
        'federation_id' => $federation->id,
        'club_id' => $club->id,
        'status' => FederationMembershipStatus::Active,
        'invited_at' => now(),
        'responded_at' => now(),
    ]);
}

describe('messages and documents', function () {
    beforeEach(function () {
        connectClub($this->federation, $this->club);
    });

    it('sends a message with attachment that the club can read', function () {
        app(TenantManager::class)->runFor($this->federation, function () {
            Livewire::actingAs(federationTestAdmin())
                ->test(Messages::class)
                ->set('subject', 'Landesmeisterschaft 2027')
                ->set('body', "Liebe Vereine,\nanbei die Ausschreibung.")
                ->set('attachments', [UploadedFile::fake()->create('ausschreibung.pdf', 100, 'application/pdf')])
                ->call('send')
                ->assertHasNoErrors()
                ->assertSee('Mitteilung an 1 Verein veröffentlicht')
                ->assertSee('gelesen von 0 / 1 Vereinen');
        });

        Mail::assertSent(FederationMessageMail::class, fn ($mail) => $mail->hasTo('obfrau@musikverein.at'));

        $message = FederationMessage::query()->sole();
        $attachment = $message->attachments()->sole();

        expect($attachment->absolutePath())->toStartWith($this->federation->storagePath('private'))
            ->and(is_file($attachment->absolutePath()))->toBeTrue();

        app(TenantManager::class)->runFor($this->club->fresh(), function () use ($message, $attachment) {
            Livewire::actingAs(federationTestAdmin())
                ->test(Inbox::class)
                ->assertSee('Landesmeisterschaft 2027')
                ->assertSee('neu')
                ->call('open', $message->id)
                ->assertSee('anbei die Ausschreibung')
                ->assertSee('ausschreibung.pdf');

            expect(FederationMessage::readIdsFor($this->club, collect([$message->id])))->toBe([$message->id]);

            $this->actingAs(User::query()->first())
                ->get(route('federation-files.show', $attachment))
                ->assertOk();
        });
    });

    it('counts the notification mails against the quota of the federation', function () {
        $this->federation->update(['limit_overrides' => ['emails_per_month' => 0]]);

        app(TenantManager::class)->runFor($this->federation->fresh(), function () {
            Livewire::actingAs(federationTestAdmin())
                ->test(Messages::class)
                ->set('subject', 'Info')
                ->set('body', 'Text')
                ->call('send')
                ->assertSee('E-Mail-Kontingent eures Pakets');
        });

        expect(FederationMessage::query()->count())->toBe(0);
    });

    it('shares documents only with active member clubs', function () {
        $outsider = createTenantWithDatabase('schuetzen', 'Schützengilde');

        app(TenantManager::class)->runFor($this->federation, function () {
            Livewire::actingAs(federationTestAdmin())
                ->test(Documents::class)
                ->set('title', 'Satzung')
                ->set('file', UploadedFile::fake()->create('satzung.pdf', 50, 'application/pdf'))
                ->call('upload')
                ->assertHasNoErrors()
                ->assertSee('Satzung');
        });

        $document = FederationFile::query()->sole();

        app(TenantManager::class)->runFor($this->club->fresh(), function () {
            Livewire::actingAs(federationTestAdmin())
                ->test(Inbox::class)
                ->assertSee('Satzung');
        });

        expect($document->isAccessibleFor($this->club->fresh()))->toBeTrue()
            ->and($document->isAccessibleFor($this->federation))->toBeTrue()
            ->and($document->isAccessibleFor($outsider))->toBeFalse();

        FederationMembership::query()->sole()->update(['status' => FederationMembershipStatus::Left]);

        expect($document->isAccessibleFor($this->club->fresh()))->toBeFalse();

        app(TenantManager::class)->runFor($this->federation, function () use ($document) {
            Livewire::actingAs(federationTestAdmin())
                ->test(Documents::class)
                ->call('delete', $document->id);
        });

        expect(is_file($document->absolutePath()))->toBeFalse();
    });
});
