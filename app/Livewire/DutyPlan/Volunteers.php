<?php

namespace App\Livewire\DutyPlan;

use App\Models\DutyPlan;
use App\Models\DutyPlanRole;
use App\Models\DutyPlanVolunteer;
use App\Models\ExternalContact;
use App\Models\Member;
use App\Models\Skill;
use App\Services\ActivityLogger;
use Livewire\Component;

class Volunteers extends Component
{
    public string $search = '';

    public ?int $planId = null;

    public function mount(): void
    {
        $plan = DutyPlan::query()
            ->orderByDesc('date_from')
            ->first();

        $this->planId = $plan?->planID;
    }

    public function selectPlan(int $planId): void
    {
        DutyPlan::findOrFail($planId);

        $this->planId = $planId;
    }

    public function copyVolunteersFromPlan(int $sourcePlanId): void
    {
        if (! $this->planId || $sourcePlanId === $this->planId) {
            return;
        }

        $sourceRows = DutyPlanVolunteer::where('planID', $sourcePlanId)->get();
        $addedCount = 0;

        foreach ($sourceRows as $row) {
            $copy = DutyPlanVolunteer::firstOrCreate(
                [
                    'planID' => $this->planId,
                    'memberID' => $row->memberID,
                    'externalContactID' => $row->externalContactID,
                ],
                [
                    'active' => $row->active,
                    'weekday_mask' => $row->weekday_mask,
                ]
            );

            if ($copy->wasRecentlyCreated) {
                $addedCount++;
            }
        }

        ActivityLogger::log(
            'duty.volunteers_copied',
            'Helferliste von Dienstplan '.DutyPlan::find($sourcePlanId)?->activityName()
                .' in Dienstplan '.$this->currentPlan()?->activityName().' übernommen: '
                .$addedCount.' neu, '.($sourceRows->count() - $addedCount).' bereits vorhanden.',
            'DutyPlan',
            $this->planId
        );

        session()->flash(
            'success',
            'Helferliste wurde übernommen ('.$sourceRows->count().' Einträge).'
        );
    }

    public function toggleVolunteer(int $memberId): void
    {
        $volunteer = DutyPlanVolunteer::query()
            ->where('planID', $this->planId)
            ->where('memberID', $memberId)
            ->first();

        if (! $volunteer) {
            $volunteer = DutyPlanVolunteer::create([
                'planID' => $this->planId,
                'memberID' => $memberId,
                'active' => true,
                'weekday_mask' => 127,
            ]);

            $this->logVolunteerToggled($volunteer);

            return;
        }

        $volunteer->update([
            'active' => ! $volunteer->active,
        ]);

        $this->logVolunteerToggled($volunteer);
    }

    public function toggleWeekday(
        int $memberId,
        int $weekday
    ): void {
        if ($weekday < 1 || $weekday > 7) {
            return;
        }

        $volunteer = DutyPlanVolunteer::query()
            ->where('planID', $this->planId)
            ->where('memberID', $memberId)
            ->first();

        if (! $volunteer || ! $volunteer->active) {
            return;
        }

        $this->toggleVolunteerWeekday($volunteer, $weekday);
    }

    public function toggleExternalVolunteer(
        int $externalContactID
    ): void {
        $contact = ExternalContact::query()
            ->where('active', true)
            ->findOrFail($externalContactID);

        $volunteer = DutyPlanVolunteer::query()
            ->where('planID', $this->planId)
            ->where('externalContactID', $contact->externalContactID)
            ->first();

        if (! $volunteer) {
            $volunteer = DutyPlanVolunteer::create([
                'planID' => $this->planId,
                'externalContactID' => $contact->externalContactID,
                'active' => true,
                'weekday_mask' => 127,
            ]);

            $this->logVolunteerToggled($volunteer);

            return;
        }

        $volunteer->update([
            'active' => ! $volunteer->active,
        ]);

        $this->logVolunteerToggled($volunteer);
    }

    public function toggleExternalWeekday(
        int $externalContactID,
        int $weekday
    ): void {
        if ($weekday < 1 || $weekday > 7) {
            return;
        }

        $volunteer = DutyPlanVolunteer::query()
            ->where('planID', $this->planId)
            ->where('externalContactID', $externalContactID)
            ->first();

        if (! $volunteer || ! $volunteer->active) {
            return;
        }

        $this->toggleVolunteerWeekday($volunteer, $weekday);
    }

    public function toggleExternalContactSkill(
        int $externalContactID,
        int $skillID
    ): void {
        $contact = ExternalContact::findOrFail($externalContactID);

        $skillsBefore = $contact->skills()->pluck('tb_skills.name')->all();

        $contact->skills()->toggle($skillID);

        ActivityLogger::log(
            'duty.volunteer_skills_changed',
            'Fähigkeiten von externem Helfer "'.$contact->full_name.'" wurden geändert.',
            'ExternalContact',
            $contact->externalContactID,
            ActivityLogger::changes(
                ['skills' => $skillsBefore],
                ['skills' => $contact->skills()->pluck('tb_skills.name')->all()],
                ['skills' => 'Fähigkeiten']
            )
        );
    }

    /**
     * Schaltet einen Wochentag um und protokolliert die neue Verfügbarkeit.
     */
    private function toggleVolunteerWeekday(DutyPlanVolunteer $volunteer, int $weekday): void
    {
        $weekdaysBefore = DutyPlanRole::formatWeekdayMask((int) $volunteer->weekday_mask);

        $volunteer->setWeekdayAvailability(
            $weekday,
            ! $volunteer->isAvailableOnWeekday($weekday)
        );

        ActivityLogger::log(
            'duty.volunteer_weekdays_changed',
            'Verfügbarkeit von '.$this->volunteerLabel($volunteer).' im Dienstplan '
                .$this->currentPlan()?->activityName().' wurde geändert.',
            'DutyPlanVolunteer',
            $volunteer->planVolunteerID,
            ActivityLogger::changes(
                ['weekdays' => $weekdaysBefore],
                ['weekdays' => DutyPlanRole::formatWeekdayMask((int) $volunteer->weekday_mask)],
                ['weekdays' => 'Verfügbare Wochentage']
            )
        );
    }

    /**
     * Aufnahme bzw. Entfernen eines Helfers (Mitglied oder extern).
     */
    private function logVolunteerToggled(DutyPlanVolunteer $volunteer): void
    {
        ActivityLogger::log(
            $volunteer->active ? 'duty.volunteer_added' : 'duty.volunteer_removed',
            ucfirst($this->volunteerLabel($volunteer)).' wurde im Dienstplan '
                .$this->currentPlan()?->activityName()
                .($volunteer->active ? ' als Helfer aufgenommen.' : ' als Helfer entfernt.'),
            'DutyPlanVolunteer',
            $volunteer->planVolunteerID
        );
    }

    /**
     * Z. B. Mitglied "Max Muster" bzw. externer Helfer "Muster Erika".
     */
    private function volunteerLabel(DutyPlanVolunteer $volunteer): string
    {
        return $volunteer->is_external
            ? 'externer Helfer "'.$volunteer->externalContact?->full_name.'"'
            : 'Mitglied "'.$volunteer->member?->full_name.'"';
    }

    private function currentPlan(): ?DutyPlan
    {
        return $this->planId ? DutyPlan::find($this->planId) : null;
    }

    public function weekdayName(int $weekday): string
    {
        return match ($weekday) {
            1 => 'Mo',
            2 => 'Di',
            3 => 'Mi',
            4 => 'Do',
            5 => 'Fr',
            6 => 'Sa',
            7 => 'So',
        };
    }

    public function render()
    {
        $planVolunteersByMember = DutyPlanVolunteer::query()
            ->where('planID', $this->planId)
            ->whereNotNull('memberID')
            ->get()
            ->keyBy('memberID');

        $planVolunteersByExternal = DutyPlanVolunteer::query()
            ->where('planID', $this->planId)
            ->whereNotNull('externalContactID')
            ->get()
            ->keyBy('externalContactID');

        $members = Member::query()
            ->with('skills')
            ->where('active', 1)
            ->when(
                $this->search,
                function ($query) {
                    $query->where(function ($query) {
                        $query
                            ->where(
                                'name',
                                'like',
                                '%'.$this->search.'%'
                            )
                            ->orWhere(
                                'surname',
                                'like',
                                '%'.$this->search.'%'
                            );
                    });
                }
            )
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        $externalContacts = ExternalContact::query()
            ->with('skills')
            ->where('active', true)
            ->when(
                $this->search,
                function ($query) {
                    $query->where(function ($query) {
                        $query
                            ->where(
                                'name',
                                'like',
                                '%'.$this->search.'%'
                            )
                            ->orWhere(
                                'surname',
                                'like',
                                '%'.$this->search.'%'
                            )
                            ->orWhere(
                                'organization',
                                'like',
                                '%'.$this->search.'%'
                            )
                            ->orWhere(
                                'email',
                                'like',
                                '%'.$this->search.'%'
                            );
                    });
                }
            )
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        return view(
            'livewire.duty-plan.volunteers',
            [
                'members' => $members,
                'externalContacts' => $externalContacts,
                'planVolunteersByMember' => $planVolunteersByMember,
                'planVolunteersByExternal' => $planVolunteersByExternal,
                'plans' => DutyPlan::orderByDesc('date_from')->get(),
                'skills' => Skill::where('active', true)->orderBy('name')->get(),
            ]
        )->layout('layouts.app', [
            'title' => 'Helfer | VEMA',
            'heading' => 'Helferverwaltung',
        ]);
    }
}
