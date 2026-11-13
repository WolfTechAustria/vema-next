<?php

use App\Livewire\MemberPortal\MyDuties;
use App\Models\ActivityLog;
use App\Models\DutyCalendarShare;
use App\Models\DutyPlan;
use App\Models\DutyPlanAssignment;
use App\Models\DutyPlanEvent;
use App\Models\DutyPlanRole;
use App\Models\Member;
use App\Models\MemberDutySettings;
use App\Models\MemberEmail;
use Livewire\Livewire;

function shareMember(string $name, string $surname, bool $active = true): Member
{
    return Member::create(['gender' => 'w', 'name' => $name, 'surname' => $surname, 'active' => $active ? 1 : 0]);
}

function assignDuty(Member $member, string $dutyName = 'Bar'): DutyPlanAssignment
{
    $plan = DutyPlan::query()->firstOrCreate(['name' => 'Saison'], ['date_from' => today()->toDateString(), 'date_to' => today()->addYear()->toDateString()]);
    $role = DutyPlanRole::create(['planID' => $plan->planID, 'name' => $dutyName, 'weekday_mask' => 127]);
    $event = DutyPlanEvent::create(['planID' => $plan->planID, 'roleID' => $role->roleID, 'duty_date' => today()->addDays(3)->toDateString(), 'duty_name' => $dutyName, 'required_helpers' => 1]);

    return DutyPlanAssignment::create(['eventID' => $event->eventID, 'slot_no' => 1, 'memberID' => $member->memberID]);
}

function calendarTokenOf(Member $member): string
{
    return MemberDutySettings::forMember($member)->getOrCreateIcalToken();
}

describe('sharing', function () {
    it('finds active members by name and shares the own duties', function () {
        $owner = loginPortalMember('Muster');
        $anna = shareMember('Anna', 'Huber');
        shareMember('Anton', 'Huber', active: false);

        Livewire::test(MyDuties::class)
            ->set('shareSearch', 'anna hub')
            ->assertSee('Anna Huber')
            ->assertDontSee('Anton Huber')
            ->call('shareWith', $anna->memberID)
            ->assertSet('shareSearch', '');

        expect(DutyCalendarShare::query()->where('ownerID', $owner->memberID)->where('viewerID', $anna->memberID)->exists())->toBeTrue()
            ->and(ActivityLog::query()->where('action', 'portal.duty_share_created')->exists())->toBeTrue();
    });

    it('finds members by email address', function () {
        loginPortalMember('Muster');
        $anna = shareMember('Anna', 'Huber');
        MemberEmail::create(['memberID' => $anna->memberID, 'email' => 'Familie@Example.com']);

        Livewire::test(MyDuties::class)
            ->set('shareSearch', 'familie@example.com')
            ->assertSee('Anna Huber')
            ->set('shareSearch', 'niemand@example.com')
            ->assertSee('Kein aktives Mitglied mit dieser E-Mail-Adresse gefunden.');
    });

    it('cannot share with oneself or members outside the search result', function () {
        $owner = loginPortalMember('Muster');
        $anna = shareMember('Anna', 'Huber');

        Livewire::test(MyDuties::class)
            ->set('shareSearch', 'Muster')
            ->assertDontSee('Freigeben')
            ->call('shareWith', $owner->memberID)
            ->assertNotFound();

        Livewire::test(MyDuties::class)
            ->set('shareSearch', 'Muster')
            ->call('shareWith', $anna->memberID)
            ->assertNotFound();

        expect(DutyCalendarShare::query()->count())->toBe(0);
    });
});

describe('calendar', function () {
    it('adds shared duties to the own calendar only when switched on', function () {
        $viewer = loginPortalMember('Muster');
        $owner = shareMember('Anna', 'Huber');
        $assignment = assignDuty($owner, 'Kantine');
        $share = DutyCalendarShare::factory()->create(['ownerID' => $owner->memberID, 'viewerID' => $viewer->memberID]);
        $token = calendarTokenOf($viewer);

        $this->get(route('member-calendar.ical', $token))->assertOk()->assertDontSee('Kantine');

        Livewire::test(MyDuties::class)->call('toggleInOwnCalendar', $share->shareID);

        $this->get(route('member-calendar.ical', $token))
            ->assertOk()
            ->assertSee('Dienst von Anna Huber: Kantine')
            ->assertSee('shared-duty-assignment-'.$assignment->assignmentID);
    });

    it('offers a separate link with only the shared duties', function () {
        $viewer = loginPortalMember('Muster');
        $owner = shareMember('Anna', 'Huber');
        assignDuty($owner, 'Kantine');
        assignDuty($viewer, 'Bar');
        $share = DutyCalendarShare::factory()->create(['ownerID' => $owner->memberID, 'viewerID' => $viewer->memberID]);

        Livewire::test(MyDuties::class)
            ->call('toggleSharedLink', $share->shareID)
            ->assertSee(route('member-calendar.shared', $share->fresh()->ical_token));

        $this->get(route('member-calendar.shared', $share->fresh()->ical_token))
            ->assertOk()
            ->assertSee('Dienste Anna Huber (freigegeben)')
            ->assertSee('Dienst von Anna Huber: Kantine')
            ->assertDontSee('Bar');
    });

    it('stops showing duties once the owner ends the share or leaves the club', function () {
        $owner = loginPortalMember('Muster');
        $viewer = shareMember('Anna', 'Huber');
        assignDuty($owner, 'Kantine');
        $share = DutyCalendarShare::factory()->inOwnCalendar()->create(['ownerID' => $owner->memberID, 'viewerID' => $viewer->memberID]);
        $link = route('member-calendar.shared', $share->getOrCreateIcalToken());

        $this->get($link)->assertOk();

        $owner->update(['active' => 0]);
        $this->get($link)->assertNotFound();
        $owner->update(['active' => 1]);

        Livewire::test(MyDuties::class)->call('endShare', $share->shareID);

        expect(DutyCalendarShare::query()->exists())->toBeFalse();
        $this->get($link)->assertNotFound();
        $this->get(route('member-calendar.ical', calendarTokenOf($viewer)))->assertOk()->assertDontSee('Kantine');
    });

    it('invalidates the old separate link when renewed', function () {
        $viewer = loginPortalMember('Muster');
        $share = DutyCalendarShare::factory()->create(['viewerID' => $viewer->memberID]);
        $oldToken = $share->getOrCreateIcalToken();

        Livewire::test(MyDuties::class)->call('regenerateSharedLink', $share->shareID);

        $this->get(route('member-calendar.shared', $oldToken))->assertNotFound();
        $this->get(route('member-calendar.shared', $share->fresh()->ical_token))->assertOk();
    });
});

it('does not let members change shares of others', function () {
    loginPortalMember('Muster');
    $foreign = DutyCalendarShare::factory()->create();

    Livewire::test(MyDuties::class)->call('toggleInOwnCalendar', $foreign->shareID)->assertNotFound();
    Livewire::test(MyDuties::class)->call('toggleSharedLink', $foreign->shareID)->assertNotFound();
    Livewire::test(MyDuties::class)->call('endShare', $foreign->shareID)->assertNotFound();

    expect($foreign->fresh())->not->toBeNull()
        ->and($foreign->fresh()->in_own_calendar)->toBeFalse();
});
