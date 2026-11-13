<?php

use App\Enums\DutySwapStatus;
use App\Livewire\DutyPlan\SwapRequests;
use App\Livewire\MemberPortal\MyDuties;
use App\Mail\DutySwapAdminMail;
use App\Mail\DutySwapAnsweredMail;
use App\Mail\DutySwapRequestedMail;
use App\Models\ActivityLog;
use App\Models\DutyPlan;
use App\Models\DutyPlanAbsence;
use App\Models\DutyPlanAssignment;
use App\Models\DutyPlanVolunteer;
use App\Models\DutySwapRequest;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberEmail;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    Setting::current()->update(['duty_swap_notify_emails' => ['dienstplan@example.test']]);
});

/**
 * Aktives Mitglied mit E-Mail-Adresse, als Helfer im Dienstplan „Saison“.
 */
function swapHelper(string $name, string $surname, bool $inPlan = true): Member
{
    $member = Member::create(['gender' => 'w', 'name' => $name, 'surname' => $surname, 'active' => 1]);
    MemberEmail::create(['memberID' => $member->memberID, 'email' => strtolower($surname).'@example.test']);

    if ($inPlan) {
        joinPlan($member);
    }

    return $member;
}

function joinPlan(Member $member): void
{
    $plan = DutyPlan::query()->firstOrCreate(['name' => 'Saison'], ['date_from' => today()->toDateString(), 'date_to' => today()->addYear()->toDateString()]);
    DutyPlanVolunteer::create(['planID' => $plan->planID, 'memberID' => $member->memberID, 'active' => 1, 'weekday_mask' => 127]);
}

function actAsPortalMember(Member $member): void
{
    $email = $member->emails()->value('email');
    $account = MemberAccount::query()->firstOrCreate(['email' => $email], ['memberID' => $member->memberID]);

    test()->actingAs($account, 'member');
    session(['active_member_id' => $member->memberID]);
}

describe('swap', function () {
    it('swaps two duties directly once the other member accepts', function () {
        $requester = loginPortalMember('Muster');
        joinPlan($requester);
        $anna = swapHelper('Anna', 'Huber');
        $mine = assignDuty($requester, 'Bar', 3);
        $hers = assignDuty($anna, 'Kantine', 5);

        Livewire::test(MyDuties::class)
            ->call('openSwap', $mine->assignmentID)
            ->set('swapType', 'swap')
            ->set('swapTarget', (string) $anna->memberID)
            ->assertSee('Kantine')
            ->set('swapTargetAssignmentID', $hers->assignmentID)
            ->set('swapMessage', 'Habe da Urlaub')
            ->call('sendSwapRequest')
            ->assertHasNoErrors()
            ->assertSee('Tausch angefragt');

        Mail::assertSent(DutySwapRequestedMail::class, fn ($mail) => $mail->hasTo('huber@example.test'));

        actAsPortalMember($anna);
        $swap = DutySwapRequest::query()->sole();

        Livewire::test(MyDuties::class)
            ->assertSee('Habe da Urlaub')
            ->call('acceptSwap', $swap->swapID)
            ->assertHasNoErrors();

        expect($mine->fresh()->memberID)->toBe($anna->memberID)
            ->and($hers->fresh()->memberID)->toBe($requester->memberID)
            ->and($swap->fresh()->status)->toBe(DutySwapStatus::Completed)
            ->and(ActivityLog::query()->where('action', 'duty.swap_completed')->exists())->toBeTrue();

        Mail::assertSent(DutySwapAnsweredMail::class, fn ($mail) => $mail->hasTo('muster@example.test'));
        Mail::assertSent(DutySwapAdminMail::class, fn ($mail) => $mail->hasTo('dienstplan@example.test'));
    });

    it('cannot ask members outside the duty plan', function () {
        $requester = loginPortalMember('Muster');
        $outsider = swapHelper('Otto', 'Extern', inPlan: false);
        $mine = assignDuty($requester);

        Livewire::test(MyDuties::class)
            ->call('openSwap', $mine->assignmentID)
            ->assertDontSee('Otto Extern')
            ->set('swapTarget', (string) $outsider->memberID)
            ->call('sendSwapRequest')
            ->assertNotFound();

        expect(DutySwapRequest::query()->exists())->toBeFalse();
    });
});

describe('handover to everyone', function () {
    it('lets the first plan member take over the duty', function () {
        $requester = loginPortalMember('Muster');
        $anna = swapHelper('Anna', 'Huber');
        $berta = swapHelper('Berta', 'Bauer');
        $outsider = swapHelper('Otto', 'Extern', inPlan: false);
        $mine = assignDuty($requester);

        Livewire::test(MyDuties::class)
            ->call('openSwap', $mine->assignmentID)
            ->call('sendSwapRequest')
            ->assertHasNoErrors();

        Mail::assertSent(DutySwapRequestedMail::class, 2);
        Mail::assertNotSent(DutySwapRequestedMail::class, fn ($mail) => $mail->hasTo('extern@example.test'));

        $swap = DutySwapRequest::query()->sole();
        expect($swap->targetMemberID)->toBeNull();

        actAsPortalMember($outsider);
        Livewire::test(MyDuties::class)->assertDontSee('Übernehmen')->call('acceptSwap', $swap->swapID)->assertNotFound();

        actAsPortalMember($anna);
        Livewire::test(MyDuties::class)->call('acceptSwap', $swap->swapID)->assertHasNoErrors();

        actAsPortalMember($berta);
        Livewire::test(MyDuties::class)->call('acceptSwap', $swap->swapID)->assertHasErrors('swap');

        expect($mine->fresh()->memberID)->toBe($anna->memberID);
    });

    it('lets the requester withdraw an open request', function () {
        $requester = loginPortalMember('Muster');
        swapHelper('Anna', 'Huber');
        $mine = assignDuty($requester);

        Livewire::test(MyDuties::class)->call('openSwap', $mine->assignmentID)->call('sendSwapRequest');
        $swap = DutySwapRequest::query()->sole();

        Livewire::test(MyDuties::class)->call('cancelSwap', $swap->swapID);

        expect($swap->fresh()->status)->toBe(DutySwapStatus::Cancelled);
    });
});

describe('approval by the club', function () {
    beforeEach(fn () => Setting::current()->update(['duty_swap_requires_approval' => true]));

    it('keeps the plan unchanged until an admin approves', function () {
        $requester = loginPortalMember('Muster');
        $anna = swapHelper('Anna', 'Huber');
        $mine = assignDuty($requester);
        $swap = DutySwapRequest::factory()->create(['requesterID' => $requester->memberID, 'assignmentID' => $mine->assignmentID, 'targetMemberID' => $anna->memberID]);

        actAsPortalMember($anna);
        Livewire::test(MyDuties::class)->call('acceptSwap', $swap->swapID)->assertSee('Wartet auf Freigabe');

        expect($swap->fresh()->status)->toBe(DutySwapStatus::AwaitingApproval)
            ->and($mine->fresh()->memberID)->toBe($requester->memberID);
        Mail::assertSent(DutySwapAdminMail::class);

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(SwapRequests::class)->call('approve', $swap->swapID);

        expect($mine->fresh()->memberID)->toBe($anna->memberID)
            ->and($swap->fresh()->status)->toBe(DutySwapStatus::Completed);
        Mail::assertSent(DutySwapAnsweredMail::class, fn ($mail) => $mail->hasTo('huber@example.test') && $mail->forAcceptor);
    });

    it('leaves the duties unchanged when the admin rejects', function () {
        $requester = loginPortalMember('Muster');
        $anna = swapHelper('Anna', 'Huber');
        $mine = assignDuty($requester);
        $swap = DutySwapRequest::factory()->create([
            'requesterID' => $requester->memberID, 'assignmentID' => $mine->assignmentID,
            'targetMemberID' => $anna->memberID, 'acceptedByID' => $anna->memberID, 'status' => DutySwapStatus::AwaitingApproval,
        ]);

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(SwapRequests::class)->call('reject', $swap->swapID);

        expect($swap->fresh()->status)->toBe(DutySwapStatus::Rejected)
            ->and($mine->fresh()->memberID)->toBe($requester->memberID);
        Mail::assertSent(DutySwapAnsweredMail::class, fn ($mail) => $mail->hasTo('muster@example.test'));
    });
});

describe('rules', function () {
    it('respects the deadline before the duty starts', function () {
        Setting::current()->update(['duty_swap_deadline_hours' => 48]);
        $requester = loginPortalMember('Muster');
        $anna = swapHelper('Anna', 'Huber');
        $soon = assignDuty($requester, 'Bar', 1);
        $later = assignDuty($requester, 'Kantine', 5);

        Livewire::test(MyDuties::class)->call('openSwap', $soon->assignmentID)->assertNotFound();

        $swap = DutySwapRequest::factory()->create(['requesterID' => $requester->memberID, 'assignmentID' => $later->assignmentID, 'targetMemberID' => $anna->memberID]);

        $this->travel(4)->days();
        actAsPortalMember($anna);
        Livewire::test(MyDuties::class)->call('acceptSwap', $swap->swapID)->assertHasErrors('swap');

        expect($swap->fresh()->status)->toBe(DutySwapStatus::Expired)
            ->and($later->fresh()->memberID)->toBe($requester->memberID);
    });

    it('warns about absences but still allows taking over', function () {
        $requester = loginPortalMember('Muster');
        $anna = swapHelper('Anna', 'Huber');
        $mine = assignDuty($requester, 'Bar', 3);
        DutyPlanAbsence::create(['memberID' => $anna->memberID, 'date_from' => today()->addDays(2)->toDateString(), 'date_to' => today()->addDays(4)->toDateString()]);
        $swap = DutySwapRequest::factory()->create(['requesterID' => $requester->memberID, 'assignmentID' => $mine->assignmentID, 'targetMemberID' => $anna->memberID]);

        actAsPortalMember($anna);
        Livewire::test(MyDuties::class)
            ->assertSee('als abwesend eingetragen')
            ->call('acceptSwap', $swap->swapID)
            ->assertHasNoErrors();

        expect($mine->fresh()->memberID)->toBe($anna->memberID);
    });

    it('blocks taking over a duty at an event the member already works', function () {
        $requester = loginPortalMember('Muster');
        $anna = swapHelper('Anna', 'Huber');
        $mine = assignDuty($requester);
        DutyPlanAssignment::create(['eventID' => $mine->eventID, 'slot_no' => 2, 'memberID' => $anna->memberID]);
        $swap = DutySwapRequest::factory()->create(['requesterID' => $requester->memberID, 'assignmentID' => $mine->assignmentID]);

        actAsPortalMember($anna);
        Livewire::test(MyDuties::class)->call('acceptSwap', $swap->swapID)->assertHasErrors('swap');

        expect($mine->fresh()->memberID)->toBe($requester->memberID)
            ->and($swap->fresh()->status)->toBe(DutySwapStatus::Pending);
    });

    it('marks a request obsolete when the duty was reassigned meanwhile', function () {
        $requester = loginPortalMember('Muster');
        $anna = swapHelper('Anna', 'Huber');
        $other = swapHelper('Otto', 'Ober');
        $mine = assignDuty($requester);
        $swap = DutySwapRequest::factory()->create(['requesterID' => $requester->memberID, 'assignmentID' => $mine->assignmentID]);
        $mine->update(['memberID' => $other->memberID]);

        actAsPortalMember($anna);
        Livewire::test(MyDuties::class)->call('acceptSwap', $swap->swapID)->assertHasErrors('swap');

        expect($swap->fresh()->status)->toBe(DutySwapStatus::Obsolete)
            ->and($mine->fresh()->memberID)->toBe($other->memberID);
    });

    it('does not let members answer or withdraw requests of others', function () {
        loginPortalMember('Muster');
        joinPlan(Member::query()->sole());
        $foreign = DutySwapRequest::factory()->create(['targetMemberID' => swapHelper('Anna', 'Huber')->memberID]);

        Livewire::test(MyDuties::class)->call('acceptSwap', $foreign->swapID)->assertNotFound();
        Livewire::test(MyDuties::class)->call('declineSwap', $foreign->swapID)->assertNotFound();
        Livewire::test(MyDuties::class)->call('cancelSwap', $foreign->swapID)->assertNotFound();

        expect($foreign->fresh()->status)->toBe(DutySwapStatus::Pending);
    });

    it('offers nothing when the club switched swapping off', function () {
        Setting::current()->update(['duty_swaps_enabled' => false]);
        $requester = loginPortalMember('Muster');
        $mine = assignDuty($requester);

        Livewire::test(MyDuties::class)
            ->assertDontSee('Tauschen / abgeben')
            ->call('openSwap', $mine->assignmentID)
            ->assertForbidden();
    });
});

describe('administration', function () {
    it('lets admins change the settings', function () {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(SwapRequests::class)
            ->set('requiresApproval', true)
            ->set('deadlineHours', 24)
            ->set('notifyEmails', 'a@example.test, b@example.test')
            ->call('saveSettings')
            ->assertHasNoErrors();

        $setting = Setting::current();
        expect($setting->duty_swap_requires_approval)->toBeTrue()
            ->and($setting->duty_swap_deadline_hours)->toBe(24)
            ->and($setting->duty_swap_notify_emails)->toBe(['a@example.test', 'b@example.test']);
    });

    it('lets other staff approve swaps but not change the settings', function () {
        $this->actingAs(User::factory()->create());

        Livewire::test(SwapRequests::class)
            ->assertSee('Nur Administratoren können die Einstellungen ändern.')
            ->call('saveSettings')
            ->assertForbidden();
    });
});
