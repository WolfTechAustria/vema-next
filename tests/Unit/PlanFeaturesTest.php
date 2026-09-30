<?php

use App\Enums\Feature;
use App\Enums\Plan;
use App\Http\Middleware\IdentifyTenant;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Members\Create as MembersCreate;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MembershipPeriodService;
use App\Services\PlanEntitlements;
use App\Services\TenantManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Legt aktive Mitglieder in der Datenbank des aktuellen Vereins an.
 */
function insertActiveMembers(int $count): void
{
    DB::table('tb_members')->insert(array_map(fn (int $number) => [
        'gender' => 'm',
        'name' => 'Max',
        'surname' => 'Muster '.$number,
        'street' => 'Hauptstraße '.$number,
        'zip' => 6320,
        'active' => 1,
    ], range(1, $count)));
}

/**
 * Staff-Sitzung (Benutzer #1 „vorstand“) für den angegebenen Verein.
 *
 * @return array<string, mixed>
 */
function staffSessionFor(Tenant $tenant): array
{
    return [
        IdentifyTenant::SESSION_KEY => $tenant->id,
        Auth::guard('web')->getName() => 1,
    ];
}

describe('plan definitions', function () {
    it('grows with every package', function () {
        expect(Plan::Starter->features())->toBe([])
            ->and(Plan::Basis->includes(Feature::Templates))->toBeTrue()
            ->and(Plan::Basis->includes(Feature::DutyPlan))->toBeFalse()
            ->and(Plan::Verein->includes(Feature::DutyPlan))->toBeTrue()
            ->and(Plan::Verein->includes(Feature::MemberPortal))->toBeFalse()
            ->and(Plan::VereinPlus->features())->toBe(Feature::cases())
            ->and(Plan::Grossverein->features())->toBe(Feature::cases());

        expect(Plan::lowestWith(Feature::CashBookReceipts))->toBe(Plan::Basis)
            ->and(Plan::lowestWith(Feature::Invoices))->toBe(Plan::Verein)
            ->and(Plan::lowestWith(Feature::TestMode))->toBe(Plan::VereinPlus);
    });

    it('matches the limits of the price list', function () {
        expect([Plan::Starter->memberLimit(), Plan::Basis->memberLimit(), Plan::Verein->memberLimit(), Plan::VereinPlus->memberLimit(), Plan::Grossverein->memberLimit()])
            ->toBe([10, 50, 150, 500, null])
            ->and([Plan::Starter->staffLimit(), Plan::Basis->staffLimit(), Plan::Verein->staffLimit(), Plan::VereinPlus->staffLimit(), Plan::Grossverein->staffLimit()])
            ->toBe([1, 2, 3, 10, null]);
    });

    it('reads the plan from website links', function () {
        expect(Plan::fromWebsite('Verein Plus'))->toBe(Plan::VereinPlus)
            ->and(Plan::fromWebsite('basis'))->toBe(Plan::Basis)
            ->and(Plan::fromWebsite('Großverein'))->toBe(Plan::Grossverein)
            ->and(Plan::fromWebsite('gibts-nicht'))->toBeNull();
    });

    it('gives all features during the trial and the booked plan afterwards', function () {
        $tenant = new Tenant(['plan' => Plan::Starter, 'trial_ends_at' => now()->addDays(10)]);

        expect($tenant->effectivePlan())->toBe(Plan::VereinPlus)
            ->and($tenant->trialDaysLeft())->toBe(10);

        $tenant->trial_ends_at = now()->subMinute();
        expect($tenant->effectivePlan())->toBe(Plan::Starter);

        // Größeres gebuchtes Paket wird durch die Testphase nicht kleiner.
        $tenant->plan = Plan::Grossverein;
        $tenant->trial_ends_at = now()->addDays(10);
        expect($tenant->effectivePlan())->toBe(Plan::Grossverein);
    });

    it('does not restrict single installations', function () {
        $entitlements = app(PlanEntitlements::class);

        foreach (Feature::cases() as $feature) {
            expect($entitlements->allows($feature))->toBeTrue();
        }

        expect($entitlements->memberLimit())->toBeNull()
            ->and($entitlements->staffLimit())->toBeNull();
    });
});

describe('feature gates on the platform', function () {
    beforeEach(function () {
        bootMultiTenancyForTests();

        $this->starter = createTenantWithDatabase('starter-verein', 'Starter Verein', ['plan' => Plan::Starter]);
        $this->verein = createTenantWithDatabase('verein-paket', 'Paket Verein', ['plan' => Plan::Verein]);
        $this->trial = createTenantWithDatabase('test-verein', 'Test Verein', ['plan' => Plan::Starter, 'trial_ends_at' => now()->addDays(20)]);
    });

    afterEach(function () {
        shutdownMultiTenancyForTests();
    });

    it('blocks and hides modules outside the package', function () {
        $this->withSession(staffSessionFor($this->starter))
            ->get('http://starter-verein.vemat.test/duty-plan')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'ab Paket „Verein“'));

        $this->withSession(staffSessionFor($this->starter))
            ->get('http://starter-verein.vemat.test/dashboard')
            ->assertOk()
            ->assertDontSee('starter-verein.vemat.test/duty-plan', false)
            ->assertDontSee('starter-verein.vemat.test/invoices', false);
    });

    it('allows modules included in the package or the trial', function () {
        $this->withSession(staffSessionFor($this->verein))
            ->get('http://verein-paket.vemat.test/duty-plan')
            ->assertOk();
    });

    it('opens the member portal during the trial', function () {
        // Mitgliederportal gibt es erst ab Verein Plus — in der Testphase schon.
        $this->get('http://test-verein.vemat.test/member/login')->assertOk();
    });

    it('closes the member portal and the calendar feed outside the package', function () {
        $this->get('http://starter-verein.vemat.test/member/login')
            ->assertForbidden()
            ->assertSee('Mitgliederportal');

        $this->get('http://starter-verein.vemat.test/calendar/duty/irgendein-token.ics')
            ->assertNotFound();

        $this->get('http://starter-verein.vemat.test/login')
            ->assertOk()
            ->assertDontSee('Zum Mitglieder-Login');
    });

    it('enforces the member limit', function () {
        app(TenantManager::class)->runFor($this->starter, function () {
            insertActiveMembers(10);

            Livewire::actingAs(User::query()->first())
                ->test(MembersCreate::class)
                ->set('gender', 'w')
                ->set('name', 'Anna')
                ->set('surname', 'Neu')
                ->call('save')
                ->assertHasErrors('memberLimit')
                ->assertSee('bis zu 10 aktive Mitglieder');

            expect(Member::query()->count())->toBe(10);
        });
    });

    it('enforces the limit when a member re-enters', function () {
        app(TenantManager::class)->runFor($this->starter, function () {
            insertActiveMembers(10);
            $formerMember = Member::query()->first();
            $formerMember->update(['active' => 0]);
            insertActiveMembers(1);

            expect(fn () => app(MembershipPeriodService::class)->reenter($formerMember, now()->toDateString()))
                ->toThrow(HttpException::class, 'bis zu 10 aktive Mitglieder');
        });
    });

    it('enforces the staff account limit', function () {
        app(TenantManager::class)->runFor($this->starter, function () {
            insertActiveMembers(1);

            Livewire::actingAs(User::query()->first())
                ->test(UsersIndex::class)
                ->set('selectedMemberId', Member::query()->value('memberID'))
                ->set('newUsername', 'kassierin')
                ->set('newEmail', 'kassierin@example.at')
                ->call('createUser')
                ->assertSee('1 Vorstandszugang');

            expect(User::query()->count())->toBe(1);
        });
    });

    it('skips duty reminders for packages without duty plan', function () {
        app(TenantManager::class)->runFor($this->starter, function () {
            $this->artisan('duty:send-reminders')
                ->expectsOutputToContain('nicht enthalten')
                ->assertSuccessful();
        });
    });

    it('shows the package in the settings', function () {
        app(TenantManager::class)->runFor($this->trial, function () {
            User::query()->first()->assignRole(Role::findOrCreate('admin', 'web'));
        });

        $this->withSession(staffSessionFor($this->trial))
            ->get('http://test-verein.vemat.test/settings')
            ->assertOk()
            ->assertSee('Paket: Verein Plus')
            ->assertSee('Testphase mit allen Funktionen bis');
    });
});
