<?php

namespace App\Livewire\MemberPortal;

use App\Enums\Feature;
use App\Models\DutyCalendarShare;
use App\Models\Member;
use App\Models\MemberDutySettings;
use App\Services\ActivityLogger;
use App\Services\DutyCalendarShares;
use App\Services\PlanEntitlements;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MyDuties extends Component
{
    public bool $reminderEnabled = true;

    public string $icalUrl = '';

    /** Suche nach Name oder E-Mail-Adresse für eine neue Freigabe */
    public string $shareSearch = '';

    /** Freigabe, deren eigener Abo-Link angezeigt wird */
    public ?int $visibleLinkShareID = null;

    public function mount(): void
    {
        $member = $this->activeMember();

        $settings = MemberDutySettings::forMember($member);

        $this->reminderEnabled = $settings->duty_reminder_enabled;

        $this->icalUrl = route(
            'member-calendar.ical',
            $settings->getOrCreateIcalToken()
        );
    }

    public function toggleReminder(): void
    {
        $member = $this->activeMember();

        $settings = MemberDutySettings::forMember($member);

        $settings->duty_reminder_enabled = ! $settings->duty_reminder_enabled;
        $settings->save();

        $this->reminderEnabled = $settings->duty_reminder_enabled;

        ActivityLogger::log(
            'portal.duty_reminder_toggled',
            'Mitglied "'.$member->full_name.'" (#'.$member->memberID.') hat die E-Mail-Erinnerung an Dienste '
                .($this->reminderEnabled ? 'aktiviert.' : 'deaktiviert.'),
            'Member',
            $member->memberID,
            ActivityLogger::changes(
                ['duty_reminder_enabled' => ! $this->reminderEnabled],
                ['duty_reminder_enabled' => $this->reminderEnabled],
                ['duty_reminder_enabled' => 'E-Mail-Erinnerung an Dienste']
            )
        );

        session()->flash(
            'success',
            $this->reminderEnabled
                ? 'E-Mail-Erinnerung wurde aktiviert.'
                : 'E-Mail-Erinnerung wurde deaktiviert.'
        );
    }

    public function regenerateIcalLink(): void
    {
        $member = $this->activeMember();

        $settings = MemberDutySettings::forMember($member);

        $previousToken = $settings->ical_token;

        $token = $settings->regenerateIcalToken();

        $this->icalUrl = route('member-calendar.ical', $token);

        ActivityLogger::log(
            'portal.calendar_link_renewed',
            'Mitglied "'.$member->full_name.'" (#'.$member->memberID.') hat seinen Kalender-Link erneuert.',
            'Member',
            $member->memberID,
            ActivityLogger::changes(
                ['ical_token' => $previousToken],
                ['ical_token' => $token],
                ['ical_token' => 'Kalender-Link'],
                ['ical_token']
            )
        );

        session()->flash(
            'success',
            'Der Kalender-Link wurde erneuert. Der alte Link funktioniert nicht mehr.'
        );
    }

    /**
     * Eigene Dienste einem anderen Mitglied freigeben.
     */
    public function shareWith(int $memberID, DutyCalendarShares $shares): void
    {
        $this->ensureSharingAvailable();

        $owner = $this->activeMember();
        $candidates = $this->shareCandidates($owner, $shares);

        abort_unless($candidates->contains('memberID', $memberID), 404);

        $viewer = Member::query()->findOrFail($memberID);
        $shares->share($owner, $viewer);

        $this->shareSearch = '';

        session()->flash('success', 'Deine Dienste sind jetzt für '.$viewer->full_name.' freigegeben.');
    }

    /**
     * Freigabe beenden – als freigebendes oder als empfangendes Mitglied.
     */
    public function endShare(int $shareID, DutyCalendarShares $shares): void
    {
        $member = $this->activeMember();
        $share = $this->shareInvolving($member, $shareID);

        $shares->end($share, $member);

        session()->flash('success', 'Die Freigabe wurde beendet.');
    }

    public function toggleInOwnCalendar(int $shareID): void
    {
        $this->ensureSharingAvailable();

        $share = $this->receivedShare($shareID);
        $share->update(['in_own_calendar' => ! $share->in_own_calendar]);
    }

    public function toggleSharedLink(int $shareID): void
    {
        $this->ensureSharingAvailable();

        $share = $this->receivedShare($shareID);
        $share->getOrCreateIcalToken();

        $this->visibleLinkShareID = $this->visibleLinkShareID === $share->shareID ? null : $share->shareID;
    }

    public function regenerateSharedLink(int $shareID): void
    {
        $this->ensureSharingAvailable();

        $this->receivedShare($shareID)->regenerateIcalToken();
        $this->visibleLinkShareID = $shareID;

        session()->flash('success', 'Der Link wurde erneuert. Der alte Link funktioniert nicht mehr.');
    }

    /**
     * @return Collection<int, Member>
     */
    private function shareCandidates(Member $owner, DutyCalendarShares $shares): Collection
    {
        $search = trim($this->shareSearch);
        $alreadyShared = $owner->dutySharesGiven()->pluck('viewerID')->all();

        $candidates = str_contains($search, '@')
            ? $shares->searchByEmail($owner, $search)
            : $shares->searchByName($owner, $search);

        return $candidates->reject(fn (Member $member) => in_array($member->memberID, $alreadyShared, true))->values();
    }

    private function receivedShare(int $shareID): DutyCalendarShare
    {
        return DutyCalendarShare::query()
            ->active()
            ->where('viewerID', $this->activeMember()->memberID)
            ->find($shareID) ?? abort(404);
    }

    private function shareInvolving(Member $member, int $shareID): DutyCalendarShare
    {
        return DutyCalendarShare::query()
            ->where(fn ($query) => $query->where('ownerID', $member->memberID)->orWhere('viewerID', $member->memberID))
            ->find($shareID) ?? abort(404);
    }

    private function ensureSharingAvailable(): void
    {
        abort_unless(app(PlanEntitlements::class)->allows(Feature::DutyPlan), 403);
    }

    private function activeMember(): Member
    {
        $account = Auth::guard('member')->user();

        abort_unless($account, 403);

        $memberID = session('active_member_id');

        abort_unless($memberID, 403, 'Kein Mitgliederprofil ausgewählt.');

        return Member::query()
            ->where('memberID', $memberID)
            ->where('active', true)
            ->whereHas('emails', function ($query) use ($account) {
                $query->where('email', $account->email);
            })
            ->firstOrFail();
    }

    public function render()
    {
        $member = $this->activeMember()
            ->load(['dutyAssignments.event']);

        $assignments = $member->dutyAssignments
            ->filter(fn ($assignment) => $assignment->event !== null)
            ->sortBy(fn ($assignment) => $assignment->event->duty_date)
            ->values();

        $upcoming = $assignments->filter(
            fn ($assignment) => $assignment->event->duty_date->isFuture()
                || $assignment->event->duty_date->isToday()
        )->values();

        $past = $assignments->filter(
            fn ($assignment) => $assignment->event->duty_date->isPast()
                && ! $assignment->event->duty_date->isToday()
        )->reverse()->values();

        $sharingAvailable = app(PlanEntitlements::class)->allows(Feature::DutyPlan);

        return view('livewire.member-portal.my-duties', [
            'upcoming' => $upcoming,
            'past' => $past,
            'sharingAvailable' => $sharingAvailable,
            'sharesGiven' => $sharingAvailable ? $member->dutySharesGiven()->with('viewer')->get()->filter(fn (DutyCalendarShare $share) => $share->viewer?->active)->sortBy('viewer.surname')->values() : collect(),
            'sharesReceived' => $sharingAvailable ? DutyCalendarShare::query()->active()->where('viewerID', $member->memberID)->with('owner')->get()->sortBy('owner.surname')->values() : collect(),
            'shareCandidates' => $sharingAvailable ? $this->shareCandidates($member, app(DutyCalendarShares::class)) : collect(),
        ])->layout('layouts.app', [
            'title' => 'Meine Dienste | VEMA',
            'heading' => 'Meine Dienste',
        ]);
    }
}
