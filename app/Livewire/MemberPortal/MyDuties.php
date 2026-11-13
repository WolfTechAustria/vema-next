<?php

namespace App\Livewire\MemberPortal;

use App\Enums\DutySwapStatus;
use App\Enums\DutySwapType;
use App\Enums\Feature;
use App\Models\DutyCalendarShare;
use App\Models\DutyPlanAssignment;
use App\Models\DutyPlanVolunteer;
use App\Models\DutySwapRequest;
use App\Models\Member;
use App\Models\MemberDutySettings;
use App\Services\ActiveMemberResolver;
use App\Services\ActivityLogger;
use App\Services\DutyCalendarShares;
use App\Services\DutySwaps;
use App\Services\PlanEntitlements;
use Illuminate\Support\Collection;
use Livewire\Component;

class MyDuties extends Component
{
    public bool $reminderEnabled = true;

    public string $icalUrl = '';

    /** Suche nach Name oder E-Mail-Adresse für eine neue Freigabe */
    public string $shareSearch = '';

    /** Freigabe, deren eigener Abo-Link angezeigt wird */
    public ?int $visibleLinkShareID = null;

    /** Dienst, für den gerade eine Tausch-/Abgabeanfrage erstellt wird */
    public ?int $swapAssignmentID = null;

    /** "handover" oder "swap" */
    public string $swapType = 'handover';

    /** memberID des angefragten Mitglieds oder "all" (Abgabe an alle) */
    public string $swapTarget = 'all';

    /** Gewünschter Dienst des anderen Mitglieds (nur beim Tausch) */
    public ?int $swapTargetAssignmentID = null;

    public string $swapMessage = '';

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
     * Formular für eine Tausch- bzw. Abgabeanfrage öffnen.
     */
    public function openSwap(int $assignmentID, DutySwaps $swaps): void
    {
        $this->ensureSwapsAvailable($swaps);

        $assignment = $this->offerableAssignment($assignmentID, $swaps);

        $this->resetErrorBag();
        $this->swapAssignmentID = $assignment->assignmentID;
        $this->swapType = DutySwapType::Handover->value;
        $this->swapTarget = 'all';
        $this->swapTargetAssignmentID = null;
        $this->swapMessage = '';
    }

    public function closeSwap(): void
    {
        $this->swapAssignmentID = null;
        $this->resetErrorBag();
    }

    public function updatedSwapType(): void
    {
        if ($this->swapType === DutySwapType::Swap->value && $this->swapTarget === 'all') {
            $this->swapTarget = '';
        }

        $this->swapTargetAssignmentID = null;
    }

    public function updatedSwapTarget(): void
    {
        $this->swapTargetAssignmentID = null;
    }

    public function sendSwapRequest(DutySwaps $swaps): void
    {
        $this->ensureSwapsAvailable($swaps);

        $member = $this->activeMember();
        $assignment = $this->offerableAssignment((int) $this->swapAssignmentID, $swaps);
        $type = DutySwapType::tryFrom($this->swapType) ?? abort(404);

        $this->validate([
            'swapTarget' => ['required', 'string'],
            'swapMessage' => ['nullable', 'string', 'max:500'],
            'swapTargetAssignmentID' => [$type === DutySwapType::Swap ? 'required' : 'nullable', 'integer'],
        ], [
            'swapTarget.required' => 'Bitte ein Mitglied wählen.',
            'swapTargetAssignmentID.required' => 'Bitte den gewünschten Dienst wählen.',
        ]);

        $target = null;
        $targetAssignment = null;

        if ($this->swapTarget !== 'all' || $type === DutySwapType::Swap) {
            $target = $swaps->candidates($member, $assignment)->firstWhere('memberID', (int) $this->swapTarget) ?? abort(404);
        }

        if ($type === DutySwapType::Swap) {
            $targetAssignment = $swaps->targetAssignments($target, $assignment)
                ->firstWhere('assignmentID', (int) $this->swapTargetAssignmentID) ?? abort(404);
        }

        $swaps->request($member, $assignment, $type, $target, $targetAssignment, $this->swapMessage);

        $this->swapAssignmentID = null;

        session()->flash('success', $target
            ? 'Deine Anfrage wurde an '.$target->full_name.' gesendet.'
            : 'Dein Dienst wurde allen im Dienstplan zur Übernahme angeboten.');
    }

    public function acceptSwap(int $swapID, DutySwaps $swaps): void
    {
        $this->ensureSwapsAvailable($swaps);

        $member = $this->activeMember();
        $swap = DutySwapRequest::query()->find($swapID);

        abort_unless($swap && $swaps->canRespond($swap, $member), 404);

        $swap = $swaps->accept($swap, $member);

        session()->flash('success', $swap->status === DutySwapStatus::AwaitingApproval
            ? 'Danke! Der Tausch wird nach der Freigabe durch den Verein im Dienstplan übernommen.'
            : 'Erledigt – der Dienstplan wurde angepasst.');
    }

    public function declineSwap(int $swapID, DutySwaps $swaps): void
    {
        $swap = DutySwapRequest::query()->find($swapID) ?? abort(404);

        $swaps->decline($swap, $this->activeMember());

        session()->flash('success', 'Die Anfrage wurde abgelehnt.');
    }

    public function cancelSwap(int $swapID, DutySwaps $swaps): void
    {
        $swap = DutySwapRequest::query()->find($swapID) ?? abort(404);

        $swaps->cancel($swap, $this->activeMember());

        session()->flash('success', 'Die Anfrage wurde zurückgezogen.');
    }

    private function ensureSwapsAvailable(DutySwaps $swaps): void
    {
        abort_unless(app(PlanEntitlements::class)->allows(Feature::DutyPlan) && $swaps->isEnabled(), 403);
    }

    private function offerableAssignment(int $assignmentID, DutySwaps $swaps): DutyPlanAssignment
    {
        $assignment = DutyPlanAssignment::query()->with('event')->find($assignmentID);

        abort_unless($assignment && $swaps->canOffer($this->activeMember(), $assignment), 404);

        return $assignment;
    }

    /**
     * Offene Anfragen, auf die das Mitglied antworten kann, und solche, die
     * es angenommen hat und die noch auf die Freigabe warten.
     *
     * @return Collection<int, DutySwapRequest>
     */
    private function incomingSwaps(Member $member): Collection
    {
        $planIDs = DutyPlanVolunteer::query()
            ->where('memberID', $member->memberID)
            ->where('active', 1)
            ->pluck('planID');

        return DutySwapRequest::query()
            ->with(['requester', 'assignment.event.role', 'targetAssignment.event'])
            ->where('requesterID', '!=', $member->memberID)
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query
                    ->where('status', DutySwapStatus::Pending)
                    ->where(fn ($query) => $query
                        ->where('targetMemberID', $member->memberID)
                        ->orWhere(fn ($query) => $query
                            ->whereNull('targetMemberID')
                            ->whereHas('assignment.event', fn ($query) => $query->whereIn('planID', $planIDs)))))
                ->orWhere(fn ($query) => $query
                    ->where('status', DutySwapStatus::AwaitingApproval)
                    ->where('acceptedByID', $member->memberID)))
            ->get()
            ->filter(fn (DutySwapRequest $swap) => $swap->assignment?->event !== null)
            ->sortBy(fn (DutySwapRequest $swap) => $swap->assignment->event->duty_date)
            ->values();
    }

    /**
     * Eigene Anfragen: offene und die der letzten 30 Tage.
     *
     * @return Collection<int, DutySwapRequest>
     */
    private function outgoingSwaps(Member $member): Collection
    {
        return DutySwapRequest::query()
            ->with(['targetMember', 'acceptedBy', 'assignment.event', 'targetAssignment.event'])
            ->where('requesterID', $member->memberID)
            ->where(fn ($query) => $query
                ->whereIn('status', DutySwapStatus::open())
                ->orWhere('updated_at', '>=', now()->subDays(30)))
            ->latest()
            ->get()
            ->filter(fn (DutySwapRequest $swap) => $swap->assignment?->event !== null)
            ->values();
    }

    /**
     * Daten für das geöffnete Anfrage-Formular.
     *
     * @return array{assignment: DutyPlanAssignment, candidates: Collection<int, Member>, targetAssignments: Collection<int, DutyPlanAssignment>, warnings: list<string>}|null
     */
    private function swapForm(Member $member, DutySwaps $swaps): ?array
    {
        if ($this->swapAssignmentID === null) {
            return null;
        }

        $assignment = DutyPlanAssignment::query()->with('event.role')->find($this->swapAssignmentID);

        if (! $assignment || ! $swaps->canOffer($member, $assignment)) {
            $this->swapAssignmentID = null;

            return null;
        }

        $candidates = $swaps->candidates($member, $assignment);
        $target = $candidates->firstWhere('memberID', (int) $this->swapTarget);
        $targetAssignments = $target && $this->swapType === DutySwapType::Swap->value
            ? $swaps->targetAssignments($target, $assignment)
            : collect();

        $warnings = [];

        if ($target) {
            $warnings = $swaps->warningsFor($target, $assignment->event, $this->swapTargetAssignmentID);

            $wanted = $targetAssignments->firstWhere('assignmentID', (int) $this->swapTargetAssignmentID);

            if ($wanted) {
                $warnings = [...$warnings, ...$swaps->warningsFor($member, $wanted->event, $assignment->assignmentID)];
            }
        }

        return [
            'assignment' => $assignment,
            'candidates' => $candidates,
            'targetAssignments' => $targetAssignments,
            'warnings' => $warnings,
        ];
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
        return app(ActiveMemberResolver::class)->resolve();
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

        $swaps = app(DutySwaps::class);
        $swapsAvailable = $sharingAvailable && $swaps->isEnabled();

        if ($swapsAvailable) {
            $swaps->expireStale();
        }

        $openSwapAssignmentIDs = $swapsAvailable
            ? DutySwapRequest::query()
                ->open()
                ->where(fn ($query) => $query
                    ->whereIn('assignmentID', $upcoming->pluck('assignmentID'))
                    ->orWhereIn('targetAssignmentID', $upcoming->pluck('assignmentID')))
                ->get(['assignmentID', 'targetAssignmentID'])
                ->flatMap(fn (DutySwapRequest $swap) => [$swap->assignmentID, $swap->targetAssignmentID])
                ->filter()
                ->all()
            : [];

        return view('livewire.member-portal.my-duties', [
            'upcoming' => $upcoming,
            'past' => $past,
            'sharingAvailable' => $sharingAvailable,
            'sharesGiven' => $sharingAvailable ? $member->dutySharesGiven()->with('viewer')->get()->filter(fn (DutyCalendarShare $share) => $share->viewer?->active)->sortBy('viewer.surname')->values() : collect(),
            'sharesReceived' => $sharingAvailable ? DutyCalendarShare::query()->active()->where('viewerID', $member->memberID)->with('owner')->get()->sortBy('owner.surname')->values() : collect(),
            'shareCandidates' => $sharingAvailable ? $this->shareCandidates($member, app(DutyCalendarShares::class)) : collect(),
            'swapsAvailable' => $swapsAvailable,
            'swaps' => $swaps,
            'member' => $member,
            'incomingSwaps' => $swapsAvailable ? $this->incomingSwaps($member) : collect(),
            'outgoingSwaps' => $swapsAvailable ? $this->outgoingSwaps($member) : collect(),
            'openSwapAssignmentIDs' => $openSwapAssignmentIDs,
            'swapForm' => $swapsAvailable ? $this->swapForm($member, $swaps) : null,
        ])->layout('layouts.app', [
            'title' => 'Meine Dienste | VEMA',
            'heading' => 'Meine Dienste',
        ]);
    }
}
