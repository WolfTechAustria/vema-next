<?php

use App\Livewire\Admin\ActivityLog\Index as ActivityLogIndex;
use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Livewire\MemberPortal\Profile;
use App\Livewire\Members\Edit as MembersEdit;
use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\MemberEmail;
use App\Models\User;
use App\Services\ActivityLogger;
use Livewire\Livewire;

describe('change detection', function () {
    it('lists only changed fields with formatted values', function () {
        $changes = ActivityLogger::changes(
            ['name' => 'Max', 'active' => 6300, 'born' => '1980-05-01', 'tags' => ['b', 'a'], 'note' => ''],
            ['name' => 'Moritz', 'active' => '6300', 'born' => now()->setDate(1980, 5, 1)->startOfDay(), 'tags' => ['a', 'b'], 'note' => null],
            ['name' => 'Vorname', 'active' => 'PLZ', 'born' => 'Geburtsdatum', 'tags' => 'Tags', 'note' => 'Notiz'],
        );

        expect($changes)->toBe([
            ['field' => 'Vorname', 'old' => 'Max', 'new' => 'Moritz', 'masked' => false],
        ]);
    });

    it('masks sensitive fields', function () {
        $changes = ActivityLogger::changes(['iban' => 'AT11'], ['iban' => 'AT22'], ['iban' => 'IBAN'], ['iban']);

        expect($changes)->toBe([
            ['field' => 'IBAN', 'old' => null, 'new' => null, 'masked' => true],
        ]);
    });

    it('stores no changes when nothing changed', function () {
        ActivityLogger::log('test.action', 'Nichts geändert', changes: []);

        expect(ActivityLog::query()->sole()->changes)->toBeNull();
    });
});

describe('members', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->admin()->create());
    });

    it('records which member fields staff changed', function () {
        $member = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'street' => 'Dorfstraße 1', 'active' => 1]);
        MemberEmail::create(['memberID' => $member->memberID, 'email' => 'alt@example.com']);

        Livewire::test(MembersEdit::class, ['member' => $member])
            ->set('street', 'Hauptplatz 2')
            ->set('emails.0.email', 'neu@example.com')
            ->call('save')
            ->assertHasNoErrors();

        $entry = ActivityLog::query()->where('action', 'member.updated')->sole();

        expect($entry->changes)->toBe([
            ['field' => 'Straße', 'old' => 'Dorfstraße 1', 'new' => 'Hauptplatz 2', 'masked' => false],
            ['field' => 'E-Mail-Adressen', 'old' => 'alt@example.com', 'new' => 'neu@example.com', 'masked' => false],
        ]);
    });

    it('records profile changes members make in the portal', function () {
        $member = loginPortalMember();
        $member->update(['street' => 'Alte Gasse 1', 'zip' => 6300]);

        Livewire::test(Profile::class)
            ->set('street', 'Neue Gasse 2')
            ->call('saveProfile')
            ->assertHasNoErrors();

        $entry = ActivityLog::query()->where('action', 'portal.profile_updated')->sole();

        expect($entry->memberAccountID)->not->toBeNull()
            ->and($entry->changes)->toBe([
                ['field' => 'Straße', 'old' => 'Alte Gasse 1', 'new' => 'Neue Gasse 2', 'masked' => false],
            ]);
    });
});

describe('settings', function () {
    it('records changed settings and masks bank details', function () {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(SettingsIndex::class)
            ->set('name', 'Schützengilde Musterdorf')
            ->set('iban', 'AT611904300234573201')
            ->call('save')
            ->assertHasNoErrors();

        $changes = collect(ActivityLog::query()->where('action', 'settings.updated')->sole()->changes)->keyBy('field');

        expect($changes['Vereinsname']['new'])->toBe('Schützengilde Musterdorf')
            ->and($changes['IBAN'])->toBe(['field' => 'IBAN', 'old' => null, 'new' => null, 'masked' => true])
            ->and($changes->has('Standard-USt.-Satz (%)'))->toBeFalse()
            ->and(json_encode(ActivityLog::query()->pluck('changes')))->not->toContain('AT611904300234573201');
    });
});

describe('activity log page', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->admin()->create());
    });

    it('shows expandable details', function () {
        ActivityLogger::log('member.updated', 'Mitglied geändert', changes: [
            ['field' => 'Straße', 'old' => 'Dorfstraße 1', 'new' => 'Hauptplatz 2', 'masked' => false],
            ['field' => 'IBAN', 'old' => null, 'new' => null, 'masked' => true],
        ]);

        Livewire::test(ActivityLogIndex::class)
            ->assertSee('Details (2)')
            ->assertSee('Hauptplatz 2')
            ->assertSee('geändert');
    });

    it('filters by area', function () {
        ActivityLogger::log('member.updated', 'Mitglied geändert');
        ActivityLogger::log('member.login', 'Mitglied angemeldet');
        ActivityLogger::log('fee.status_changed', 'Beitrag bezahlt');

        Livewire::test(ActivityLogIndex::class)
            ->set('filter', 'member')
            ->assertSee('Mitglied geändert')
            ->assertDontSee('Mitglied angemeldet')
            ->assertDontSee('Beitrag bezahlt')
            ->set('filter', 'login')
            ->assertSee('Mitglied angemeldet')
            ->assertDontSee('Mitglied geändert')
            ->set('filter', 'fee')
            ->assertSee('Beitrag bezahlt');
    });
});
