<?php

use App\Enums\DashboardModule;
use App\Livewire\Dashboard\Index;
use App\Models\DutyPlan;
use App\Models\DutyPlanAssignment;
use App\Models\DutyPlanEvent;
use App\Models\DutyPlanRole;
use App\Models\ExternalContact;
use App\Models\Member;
use App\Models\MemberMembershipPeriod;
use App\Models\User;
use App\Services\DashboardLayout;
use App\Services\MemberHistory;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * @param  list<string>  $roles
 */
function dashboardUser(array $roles = []): User
{
    $user = User::factory()->create();

    foreach ($roles as $role) {
        $user->assignRole(Role::findOrCreate($role, 'web'));
    }

    return $user;
}

/**
 * @return list<string>
 */
function dashboardKeys(User $user): array
{
    return array_map(fn (DashboardModule $module) => $module->value, app(DashboardLayout::class)->modulesFor($user));
}

test('new users see the built-in default modules in order', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Mitglieder', 'Nächste Termine', 'Nächste Dienste', 'Geburtstage', 'Mitgliedsbeiträge', 'Mitgliederentwicklung']);
});

test('modules follow the access rules of their pages', function (array $roles, array $expected) {
    $user = dashboardUser($roles);

    $available = collect([DashboardModule::CashBook, DashboardModule::OpenInvoices, DashboardModule::RecentActivity, DashboardModule::HomepagePosts, DashboardModule::Inventory])
        ->filter(fn (DashboardModule $module) => $module->isAvailableFor($user))
        ->map(fn (DashboardModule $module) => $module->value)
        ->values()
        ->all();

    expect($available)->toBe($expected);
})->with([
    'no role' => [[], []],
    'kassier' => [['kassier'], ['cash_book', 'open_invoices']],
    'homepage' => [['homepage'], ['homepage_posts']],
    'zeugwart' => [['zeugwart'], ['inventory']],
    'admin' => [['admin'], ['cash_book', 'open_invoices', 'recent_activity', 'homepage_posts', 'inventory']],
]);

test('a module without access is not shown even if it is in the layout', function () {
    $user = User::factory()->create();
    app(DashboardLayout::class)->saveClubDefault(['cash_book', 'member_stats']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Kassastand');

    $this->actingAs(dashboardUser(['kassier']))
        ->get(route('dashboard'))
        ->assertSee('Kassastand');
});

test('moving a module changes only the own layout', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('sortModule', 'birthdays', 0);

    expect(dashboardKeys($user))->toBe(['birthdays', 'member_stats', 'upcoming_events', 'upcoming_duties', 'membership_fees', 'member_history'])
        ->and(dashboardKeys($otherUser))->toBe(['member_stats', 'upcoming_events', 'upcoming_duties', 'birthdays', 'membership_fees', 'member_history']);
});

test('hidden modules can be added again at the end', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('hideModule', 'member_stats')
        ->call('showModule', 'member_stats');

    expect(dashboardKeys($user))->toBe(['upcoming_events', 'upcoming_duties', 'birthdays', 'membership_fees', 'member_history', 'member_stats']);
});

test('a module without access cannot be added', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('showModule', 'cash_book');

    expect(dashboardKeys($user))->not->toContain('cash_book');
});

test('resetting removes the own layout', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('hideModule', 'birthdays')
        ->call('resetToDefault');

    expect(dashboardKeys($user))->toContain('birthdays')
        ->and(app(DashboardLayout::class)->hasPersonalLayout($user))->toBeFalse();
});

test('admins can make their layout the club default', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('sortModule', 'member_history', 0)
        ->call('hideModule', 'upcoming_duties')
        ->call('saveAsClubDefault');

    expect(dashboardKeys(User::factory()->create()))->toBe(['member_history', 'member_stats', 'upcoming_events', 'birthdays', 'membership_fees'])
        ->and(loggedChanges('settings.dashboard_default_updated')['Module']['new'])
        ->toBe('Mitgliederentwicklung, Mitglieder, Nächste Termine, Geburtstage, Mitgliedsbeiträge');
});

test('only admins can save the club default', function () {
    Livewire::actingAs(dashboardUser(['kassier']))
        ->test(Index::class)
        ->call('saveAsClubDefault')
        ->assertForbidden();
});

test('member history counts stock at year end with joins and exits', function () {
    $this->travelTo('2026-06-15');

    $periods = [
        [1, '2020-03-01', null],
        [2, '2021-05-01', '2023-02-01'],
        [3, '2019-01-01', '2022-12-31'],
        [4, '2018-01-01', '2020-06-30'],
        [4, '2024-01-01', null],
    ];

    foreach ($periods as [$memberID, $from, $to]) {
        MemberMembershipPeriod::create(['memberID' => $memberID, 'date_from' => $from, 'date_to' => $to]);
    }

    expect(app(MemberHistory::class)->years(5))->toBe([
        ['year' => 2022, 'stock' => 2, 'joined' => 0, 'left' => 1],
        ['year' => 2023, 'stock' => 1, 'joined' => 0, 'left' => 1],
        ['year' => 2024, 'stock' => 2, 'joined' => 1, 'left' => 0],
        ['year' => 2025, 'stock' => 2, 'joined' => 0, 'left' => 0],
        ['year' => 2026, 'stock' => 2, 'joined' => 0, 'left' => 0],
    ]);
});

test('upcoming duties show who is assigned', function () {
    $plan = DutyPlan::create(['name' => 'Saison', 'date_from' => today()->toDateString(), 'date_to' => today()->addYear()->toDateString()]);
    $role = DutyPlanRole::create(['planID' => $plan->planID, 'name' => 'Bar', 'weekday_mask' => 127]);
    $staffed = DutyPlanEvent::create(['planID' => $plan->planID, 'roleID' => $role->roleID, 'duty_date' => today()->addDays(2)->toDateString(), 'duty_name' => 'Bar', 'required_helpers' => 2]);
    DutyPlanEvent::create(['planID' => $plan->planID, 'roleID' => $role->roleID, 'duty_date' => today()->addDays(9)->toDateString(), 'duty_name' => 'Bar', 'required_helpers' => 1]);

    $max = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);
    $contact = ExternalContact::create(['name' => 'Anna', 'surname' => 'Extern', 'email' => 'anna@example.com', 'active' => true]);
    DutyPlanAssignment::create(['eventID' => $staffed->eventID, 'slot_no' => 1, 'memberID' => $max->memberID]);
    DutyPlanAssignment::create(['eventID' => $staffed->eventID, 'slot_no' => 2, 'externalContactID' => $contact->externalContactID]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('2/2 besetzt')
        ->assertSee('Max Muster, '.$contact->full_name.' (extern)')
        ->assertSee('noch niemand eingeteilt');
});
