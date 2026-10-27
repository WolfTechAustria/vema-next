<?php

use App\Enums\Feature;
use App\Enums\Limit;
use App\Enums\Plan;
use App\Exceptions\MailQuotaExceededException;
use App\Livewire\Central\Admin\Plans;
use App\Livewire\Central\Admin\TenantDetail;
use App\Livewire\MembershipFees\Index as MembershipFeesIndex;
use App\Mail\Contracts\ExemptFromMailQuota;
use App\Models\Member;
use App\Models\MemberEmail;
use App\Models\MembershipFeeEntry;
use App\Models\MembershipFeeYear;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Rules\WithinStorageQuota;
use App\Services\MailQuota;
use App\Services\PlanEntitlements;
use App\Services\TenantManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
 * Pakete im Plattform-Admin, individuelle Anpassungen je Verein,
 * E-Mail-Kontingent und Speicher.
 */
uses(TestCase::class);

beforeEach(function () {
    bootMultiTenancyForTests();

    $this->admin = PlatformAdmin::create([
        'name' => 'Betreiber',
        'email' => 'office@vemat.test',
        'password' => 'ein-sehr-sicheres-passwort',
    ]);

    $this->club = createTenantWithDatabase('musikverein', 'Musikverein', ['plan' => Plan::Starter]);
});

afterEach(function () {
    shutdownMultiTenancyForTests();
});

/**
 * Einfache Testmail; mit $exempt zählt sie nicht zum Kontingent.
 */
function quotaTestMail(bool $exempt = false): Mailable
{
    $mail = $exempt
        ? new class extends Mailable implements ExemptFromMailQuota {}
    : new class extends Mailable {};

    return $mail->subject('Test')->html('<p>Test</p>');
}

describe('plans', function () {
    it('uses the price list until a plan is changed in the admin', function () {
        expect(Plan::Starter->limit(Limit::EmailsPerMonth))->toBe(100)
            ->and(Plan::Basis->limit(Limit::StorageMb))->toBe(2048)
            ->and(Plan::VereinPlus->limit(Limit::EmailsPerMonth))->toBe(10000)
            ->and(Plan::Grossverein->limit(Limit::StorageMb))->toBeNull();

        Livewire::actingAs($this->admin, 'platform')
            ->test(Plans::class)
            ->set('features.starter.duty_plan', true)
            ->set('limits.starter.emails_per_month', '250')
            ->set('limits.starter.members', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Gespeichert: Starter.');

        expect(Plan::Starter->includes(Feature::DutyPlan))->toBeTrue()
            ->and(Plan::Starter->limit(Limit::EmailsPerMonth))->toBe(250)
            ->and(Plan::Starter->memberLimit())->toBeNull()
            ->and(Plan::Basis->limit(Limit::EmailsPerMonth))->toBe(1000)
            ->and($this->club->fresh()->allows(Feature::DutyPlan))->toBeTrue();

        Livewire::actingAs($this->admin, 'platform')
            ->test(Plans::class)
            ->call('resetPlan', 'starter');

        expect(Plan::Starter->includes(Feature::DutyPlan))->toBeFalse()
            ->and(Plan::Starter->limit(Limit::EmailsPerMonth))->toBe(100);
    });

    it('rejects invalid limits', function () {
        Livewire::actingAs($this->admin, 'platform')
            ->test(Plans::class)
            ->set('limits.basis.storage_mb', '0')
            ->call('save')
            ->assertHasErrors('limits.basis.storage_mb');
    });
});

describe('club overrides', function () {
    it('switches single features and limits for one club', function () {
        Livewire::actingAs($this->admin, 'platform')
            ->test(TenantDetail::class, ['tenant' => $this->club])
            ->set('featureOverrides.duty_plan', 'on')
            ->set('featureOverrides.templates', 'off')
            ->set('limitModes.storage_mb', 'custom')
            ->set('limitValues.storage_mb', '10240')
            ->set('limitModes.members', 'unlimited')
            ->call('saveOverrides')
            ->assertHasNoErrors()
            ->assertSee('Individuelle Anpassungen geändert');

        $club = $this->club->fresh();

        expect($club->allows(Feature::DutyPlan))->toBeTrue()
            ->and($club->allows(Feature::Invoices))->toBeFalse()
            ->and($club->limit(Limit::StorageMb))->toBe(10240)
            ->and($club->limit(Limit::Members))->toBeNull()
            ->and($club->limit(Limit::EmailsPerMonth))->toBe(100)
            ->and($club->hasCustomizations())->toBeTrue();

        // Auch während der Testphase bleibt eine abgeschaltete Funktion aus.
        $club->update(['trial_ends_at' => now()->addDays(10)]);

        expect($club->fresh()->allows(Feature::Templates))->toBeFalse()
            ->and($club->fresh()->allows(Feature::Invoices))->toBeTrue();

        app(TenantManager::class)->runFor($club->fresh(), function () {
            expect(app(PlanEntitlements::class)->allows(Feature::DutyPlan))->toBeTrue()
                ->and(app(PlanEntitlements::class)->memberLimit())->toBeNull();
        });
    });

    it('requires a value for a custom limit', function () {
        Livewire::actingAs($this->admin, 'platform')
            ->test(TenantDetail::class, ['tenant' => $this->club])
            ->set('limitModes.emails_per_month', 'custom')
            ->set('limitValues.emails_per_month', '')
            ->call('saveOverrides')
            ->assertHasErrors('limitValues.emails_per_month');
    });

    it('goes back to the plan', function () {
        $this->club->update(['feature_overrides' => ['duty_plan' => true], 'limit_overrides' => ['members' => 500]]);

        Livewire::actingAs($this->admin, 'platform')
            ->test(TenantDetail::class, ['tenant' => $this->club->fresh()])
            ->assertSet('featureOverrides.duty_plan', 'on')
            ->assertSet('limitModes.members', 'custom')
            ->set('featureOverrides.duty_plan', 'plan')
            ->set('limitModes.members', 'plan')
            ->call('saveOverrides');

        $club = $this->club->fresh();

        expect($club->feature_overrides)->toBeNull()
            ->and($club->limit_overrides)->toBeNull()
            ->and($club->limit(Limit::Members))->toBe(10);
    });
});

describe('mail quota', function () {
    beforeEach(function () {
        config(['mail.default' => 'array']);
        $this->club->update(['limit_overrides' => ['emails_per_month' => 3]]);
    });

    it('counts mails per recipient and stops at the monthly limit', function () {
        app(TenantManager::class)->runFor($this->club->fresh(), function ($club) {
            Mail::to(['a@example.com', 'b@example.com'])->send(quotaTestMail());

            expect(app(MailQuota::class)->sentThisMonth($club))->toBe(2)
                ->and(app(MailQuota::class)->remaining())->toBe(1);

            Mail::to('c@example.com')->send(quotaTestMail());

            expect(fn () => Mail::to('d@example.com')->send(quotaTestMail()))
                ->toThrow(MailQuotaExceededException::class, 'es ist aufgebraucht');

            // Anmelde-Links & Co. gehen trotzdem raus und zählen nicht.
            Mail::to('e@example.com')->send(quotaTestMail(exempt: true));

            expect(app(MailQuota::class)->sentThisMonth($club))->toBe(3);
        });

        $this->travel(1)->months();

        app(TenantManager::class)->runFor($this->club->fresh(), function () {
            expect(app(MailQuota::class)->remaining())->toBe(3);
        });
    });

    it('does not count platform mails without a club', function () {
        Mail::to('office@vemat.test')->send(quotaTestMail());

        expect(DB::connection('landlord')->table('tenant_mail_usage')->count())->toBe(0);
    });

    it('refuses a bulk sending that does not fit and shows a message', function () {
        Mail::fake();

        app(TenantManager::class)->runFor($this->club->fresh(), function () {
            $year = MembershipFeeYear::create(['year' => 2026, 'name' => 'Beitrag 2026', 'default_amount' => 25, 'active' => true]);

            foreach (['Huber', 'Muster', 'Gruber', 'Maier'] as $surname) {
                $member = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => $surname, 'active' => 1]);
                MemberEmail::create(['memberID' => $member->memberID, 'email' => strtolower($surname).'@example.com']);
                MembershipFeeEntry::create(['yearID' => $year->yearID, 'memberID' => $member->memberID, 'amount' => 25, 'status' => 'open']);
            }

            $kassier = User::query()->first();
            $kassier->assignRole(Role::findOrCreate('kassier', 'web'));

            Livewire::actingAs($kassier)
                ->test(MembershipFeesIndex::class)
                ->set('yearId', $year->yearID)
                ->set('selectedEntries', MembershipFeeEntry::query()->pluck('entryID')->map(fn ($id) => (string) $id)->all())
                ->call('sendSelectedByEmail')
                ->assertSee('noch 3 frei, benötigt 4');
        });

        Mail::assertNothingSent();
    });
});

describe('storage', function () {
    it('rejects uploads beyond the storage of the plan', function () {
        $this->club->update(['limit_overrides' => ['storage_mb' => 1]]);
        $club = $this->club->fresh();

        File::ensureDirectoryExists($club->storagePath('private/members'));
        File::put($club->storagePath('private/members/scan.pdf'), str_repeat('x', 700 * 1024));

        app(TenantManager::class)->runFor($club, function () {
            $validate = fn (int $kilobytes) => Validator::make(
                ['file' => UploadedFile::fake()->create('beleg.pdf', $kilobytes)],
                ['file' => [new WithinStorageQuota]],
            );

            expect($validate(200)->passes())->toBeTrue();

            // Die 200 KB von eben zählen schon mit.
            $failed = $validate(200);

            expect($failed->fails())->toBeTrue()
                ->and($failed->errors()->first('file'))->toContain('Speicherplatz eures Pakets ist voll');
        });
    });

    it('does not limit unlimited plans or single installations', function () {
        $this->club->update(['limit_overrides' => ['storage_mb' => null]]);

        app(TenantManager::class)->runFor($this->club->fresh(), function () {
            expect(Validator::make(
                ['file' => UploadedFile::fake()->create('gross.pdf', 50 * 1024)],
                ['file' => [new WithinStorageQuota]],
            )->passes())->toBeTrue();
        });
    });
});
