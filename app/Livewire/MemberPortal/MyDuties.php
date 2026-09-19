<?php

namespace App\Livewire\MemberPortal;

use App\Models\Member;
use App\Models\MemberDutySettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MyDuties extends Component
{
    public bool $reminderEnabled = true;

    public string $icalUrl = '';

    public function mount(): void
    {
        $member = $this->activeMember();

        $settings = MemberDutySettings::forMember($member);

        $this->reminderEnabled = $settings->duty_reminder_enabled;

        $this->icalUrl = route(
            'duty-plan.ical',
            $settings->getOrCreateIcalToken()
        );
    }

    public function toggleReminder(): void
    {
        $member = $this->activeMember();

        $settings = MemberDutySettings::forMember($member);

        $settings->duty_reminder_enabled = !$settings->duty_reminder_enabled;
        $settings->save();

        $this->reminderEnabled = $settings->duty_reminder_enabled;

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

        $token = $settings->regenerateIcalToken();

        $this->icalUrl = route('duty-plan.ical', $token);

        session()->flash(
            'success',
            'Der Kalender-Link wurde erneuert. Der alte Link funktioniert nicht mehr.'
        );
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
                && !$assignment->event->duty_date->isToday()
        )->reverse()->values();

        return view('livewire.member-portal.my-duties', [
            'upcoming' => $upcoming,
            'past' => $past,
        ])->layout('layouts.app', [
            'title' => 'Meine Dienste | VEMA',
            'heading' => 'Meine Dienste',
        ]);
    }
}
