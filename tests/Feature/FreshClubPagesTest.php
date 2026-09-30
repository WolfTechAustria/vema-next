<?php

use App\Livewire\Members\Create as MembersCreate;
use App\Models\Member;
use App\Models\MembershipFeeEntry;
use App\Models\MembershipFeeYear;
use App\Models\Template;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/*
 * Ein neu registrierter Verein startet mit einer leeren Datenbank (keine
 * Beitragsjahre, Mitglieder, …). Jede Seite muss trotzdem laden.
 */

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->admin->assignRole(Role::findOrCreate('kassier', 'web'));
});

it('renders every staff page on an empty database', function (string $routeName) {
    $this->actingAs($this->admin)
        ->get(route($routeName))
        ->assertOk();
})->with([
    'dashboard',
    'members.index',
    'members.create',
    // members.birthdays nutzt DAY() — gibt es nur in MySQL, nicht in der Test-DB (SQLite).
    'duty-plan.index',
    'duty-plan.volunteers',
    'duty-plan.absences',
    'membership-fees.index',
    'templates.index',
    'external-contacts.index',
    'circulars.index',
    'recipient-groups.index',
    'skills.index',
    'admin.users.index',
    'admin.settings.index',
    'admin.settings.documents',
    'admin.activity-log.index',
    'cash-book.index',
    'cash-book.years',
    'invoices.index',
    'invoices.create',
    'invoice-recipients.index',
    'invoice-articles.index',
]);

it('renders the member login on an empty database', function () {
    $this->get(route('member.login'))->assertOk();
});

it('explains how to start when no fee year exists', function () {
    $this->actingAs($this->admin)
        ->get(route('membership-fees.index'))
        ->assertOk()
        ->assertSee('Noch kein Beitragsjahr angelegt');
});

it('creates members without an address', function () {
    Livewire::actingAs($this->admin)
        ->test(MembersCreate::class)
        ->set('gender', 'w')
        ->set('name', 'Anna')
        ->set('surname', 'Ohne-Adresse')
        ->call('save')
        ->assertHasNoErrors();

    $member = Member::query()->where('surname', 'Ohne-Adresse')->sole();

    expect($member->street)->toBeNull()
        ->and($member->zip)->toBeNull();
});

it('ships default templates for fee prescriptions and reminders', function () {
    expect(Template::query()->pluck('key')->sort()->values()->all())
        ->toBe(['membership_fee_prescription', 'membership_fee_reminder']);
});

it('creates a fee prescription for a new club', function () {
    $member = Member::query()->create([
        'gender' => 'm',
        'name' => 'Max',
        'surname' => 'Muster',
        'street' => null,
        'zip' => null,
    ]);

    $year = MembershipFeeYear::query()->create([
        'year' => (int) now()->format('Y'),
        'name' => 'Mitgliedsbeitrag '.now()->format('Y'),
        'default_amount' => 30,
        'active' => true,
    ]);

    $entry = MembershipFeeEntry::query()->create([
        'yearID' => $year->yearID,
        'memberID' => $member->memberID,
        'amount' => 30,
        'status' => 'open',
    ]);

    $this->actingAs($this->admin)
        ->get(route('membership-fees.prescription', $entry))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});
