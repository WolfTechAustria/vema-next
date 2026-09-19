<?php

namespace App\Livewire\DutyPlan;

use App\Models\DutyPlanEvent;
use Carbon\Carbon;
use Livewire\Component;
use App\Services\DutyPlanAssignmentService;
use App\Services\DutyPlanGeneratorService;
use App\Models\DutyPlanAssignment;
use App\Models\DutyPlanVolunteer;
use App\Exports\DutyPlanExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\DutyPlan;
use Livewire\Attributes\On;


class Index extends Component
{
    public ?int $deletePlanId = null;

    public ?int $planId = null;

    public string $planName = '';

    public bool $showCreatePlan = false;
    public bool $copyFromCurrentPlan = true;
    public string $dateFrom = '';
    public string $dateTo = '';

    public string $exportWeekday = 'all';
    public bool $excludeHolidays = true;

    #[On('duty-plan-roles-updated')]
    public function refreshAfterRolesUpdated(): void
    {
        // Kein eigener Zustand zu aktualisieren — das Neu-Rendern selbst
        // genügt, damit die Terminliste die aktuellen Rollen-Daten aus der
        // (bereits im RolesEditor-Kindkomponente gespeicherten) DB liest.
    }

    public function mount(): void
    {
        $plan = DutyPlan::query()
            ->orderByDesc('date_from')
            ->first();

        if ($plan) {
            $this->selectPlan($plan->planID);

            return;
        }

        $this->dateFrom = now()
            ->startOfYear()
            ->format('Y-m-d');

        $this->dateTo = now()
            ->endOfYear()
            ->format('Y-m-d');
    }

    public function selectPlan(int $planId): void
    {
        $plan = DutyPlan::findOrFail($planId);

        $this->planId = $plan->planID;

        $this->planName = $plan->name;

        $this->dateFrom = $plan->date_from->format('Y-m-d');
        $this->dateTo = $plan->date_to->format('Y-m-d');

        $this->excludeHolidays =
            (bool) $plan->exclude_holidays;

        $this->resetValidation();
    }

    public function createPlan(): void
    {
        $validated = $this->validate([
            'planName' => [
                'required',
                'string',
                'max:150',
            ],

            'dateFrom' => [
                'required',
                'date',
            ],

            'dateTo' => [
                'required',
                'date',
                'after_or_equal:dateFrom',
            ],

            'excludeHolidays' => [
                'boolean',
            ],
        ]);

        $sourcePlanId = $this->copyFromCurrentPlan ? $this->planId : null;

        $plan = DutyPlan::create([
            'name' => $validated['planName'],

            'date_from' => $validated['dateFrom'],
            'date_to' => $validated['dateTo'],

            'exclude_holidays' =>
                $validated['excludeHolidays'],
        ]);

        if ($sourcePlanId) {
            $source = DutyPlan::find($sourcePlanId);

            if ($source) {
                $this->copyRolesAndVolunteers($source, $plan);
            }
        }

        $this->selectPlan($plan->planID);

        $this->showCreatePlan = false;

        session()->flash(
            'success',
            'Dienstplan wurde angelegt.'
            . ($sourcePlanId ? ' Dienstbezeichnungen und Helferliste wurden vom vorherigen Plan übernommen.' : '')
        );
    }

    /**
     * Kopiert Dienstbezeichnungen und Helferliste (Mitglieder + externe
     * Helfer) eines bestehenden Plans in einen neuen/anderen Plan. Termine
     * und bereits erfolgte Einteilungen werden bewusst NICHT übernommen.
     */
    protected function copyRolesAndVolunteers(DutyPlan $source, DutyPlan $target): void
    {
        foreach ($source->roles as $role) {
            $target->roles()->create(
                collect($role->toArray())
                    ->except(['roleID', 'planID', 'created_at', 'updated_at'])
                    ->all()
            );
        }

        foreach (DutyPlanVolunteer::where('planID', $source->planID)->get() as $volunteer) {
            DutyPlanVolunteer::firstOrCreate(
                [
                    'planID' => $target->planID,
                    'memberID' => $volunteer->memberID,
                    'externalContactID' => $volunteer->externalContactID,
                ],
                [
                    'active' => $volunteer->active,
                    'weekday_mask' => $volunteer->weekday_mask,
                ]
            );
        }
    }

    public function savePlan(): void
    {
        if (!$this->planId) {
            return;
        }

        $validated = $this->validate([
            'planName' => [
                'required',
                'string',
                'max:150',
            ],

            'dateFrom' => [
                'required',
                'date',
            ],

            'dateTo' => [
                'required',
                'date',
                'after_or_equal:dateFrom',
            ],

            'excludeHolidays' => [
                'boolean',
            ],
        ]);

        DutyPlan::whereKey($this->planId)
            ->update([
                'name' => $validated['planName'],
                'date_from' => $validated['dateFrom'],
                'date_to' => $validated['dateTo'],
                'exclude_holidays' =>
                    $validated['excludeHolidays'],
            ]);

        session()->flash(
            'success',
            'Dienstplan wurde gespeichert.'
        );
    }

    public function duplicatePlan(): void
    {
        if (!$this->planId) {
            return;
        }

        $source = DutyPlan::findOrFail($this->planId);

        $copy = DutyPlan::create([
            'name' => $source->name . ' Kopie',
            'date_from' => $source->date_from,
            'date_to' => $source->date_to,
            'exclude_holidays' => $source->exclude_holidays,
        ]);

        $this->copyRolesAndVolunteers($source, $copy);

        $this->selectPlan($copy->planID);

        session()->flash(
            'success',
            'Dienstplan wurde dupliziert. Dienstbezeichnungen und Helferliste wurden übernommen, Termine und Einteilungen nicht.'
        );
    }

    public function deletePlan(): void
    {
        if (!$this->planId) {
            return;
        }

        $plan = DutyPlan::findOrFail($this->planId);

        $plan->delete();

        $nextPlan = DutyPlan::query()
            ->orderByDesc('date_from')
            ->first();

        if ($nextPlan) {
            $this->selectPlan($nextPlan->planID);
        } else {
            $this->planId = null;
            $this->planName = '';

            $this->dateFrom = now()->startOfYear()->format('Y-m-d');
            $this->dateTo = now()->endOfYear()->format('Y-m-d');
        }

        session()->flash('success', 'Dienstplan wurde gelöscht.');
    }

    public function exportPdf()
    {
        $weekday = $this->exportWeekday === 'all'
            ? null
            : (int) $this->exportWeekday;

        $events = DutyPlanEvent::query()
            ->where('planID', $this->planId)
            ->with([
                'assignments.member',
                'assignments.externalContact',
            ])
            ->whereBetween('duty_date', [
                $this->dateFrom,
                $this->dateTo,
            ])
            ->when(
                $weekday,
                fn ($query) =>
                $query->whereRaw(
                    'WEEKDAY(duty_date) + 1 = ?',
                    [$weekday]
                )
            )
            ->orderBy('duty_date')
            ->get();

        $weekdayName = $weekday
            ? $this->weekdayName($weekday)
            : 'Alle';

        $filename = sprintf(
            'Dienstplan_%s_%s_%s.pdf',
            $weekdayName,
            $this->dateFrom,
            $this->dateTo
        );

        $pdf = Pdf::loadView('pdf.duty-plan', [
            'events' => $events,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'weekdayName' => $weekdayName,
        ])
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename
        );
    }

    public function exportExcel()
    {
        $weekday = $this->exportWeekday === 'all'
            ? null
            : (int) $this->exportWeekday;

        $weekdayName = $weekday
            ? $this->weekdayName($weekday)
            : 'Alle';

        $filename = sprintf(
            'Dienstplan_%s_%s_%s.xlsx',
            $weekdayName,
            $this->dateFrom,
            $this->dateTo
        );

        return Excel::download(
            new DutyPlanExport(
                $this->planId,
                $this->dateFrom,
                $this->dateTo,
                $weekday
            ),
            $filename
        );
    }
    public function updateAssignment(
        int $eventId,
        int $slotNo,
        ?string $volunteerKey
    ): void {
        $assignment = DutyPlanAssignment::query()
            ->where('eventID', $eventId)
            ->where('slot_no', $slotNo)
            ->first();

        if (!$volunteerKey) {
            $assignment?->delete();

            return;
        }

        [$type, $id] = array_pad(
            explode(':', $volunteerKey, 2),
            2,
            null
        );

        $id = (int) $id;

        if (
            !in_array($type, ['member', 'external'], true)
            || $id <= 0
        ) {
            return;
        }

        $alreadyAssigned = DutyPlanAssignment::query()
            ->where('eventID', $eventId)
            ->where('slot_no', '!=', $slotNo)
            ->where(function ($query) use ($type, $id) {

                if ($type === 'member') {
                    $query->where('memberID', $id);
                } else {
                    $query->where('externalContactID', $id);
                }

            })
            ->exists();

        if ($alreadyAssigned) {
            session()->flash(
                'error',
                'Dieser Helfer ist bei diesem Termin bereits eingeteilt.'
            );

            return;
        }

        DutyPlanAssignment::updateOrCreate(
            [
                'eventID' => $eventId,
                'slot_no' => $slotNo,
            ],
            [
                'memberID' =>
                    $type === 'member'
                        ? $id
                        : null,

                'externalContactID' =>
                    $type === 'external'
                        ? $id
                        : null,
            ]
        );
    }


    protected function rules(): array
    {
        return [
            'dateFrom' => ['required', 'date'],
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom'],

            'excludeHolidays' => ['boolean'],
        ];
    }

    public function getEventsProperty()
    {
        if (!$this->planId) {
            return collect();
        }

        if (!$this->dateFrom || !$this->dateTo) {
            return collect();
        }

        return DutyPlanEvent::query()
            ->with([
                'role.requiredGroup',
                'role.requiredSkill',
                'assignments.member',
                'assignments.externalContact',
            ])
            ->where('tb_dutyplan_events.planID', $this->planId)
            ->whereBetween('tb_dutyplan_events.duty_date', [
                $this->dateFrom,
                $this->dateTo,
            ])
            ->join('tb_dutyplan_roles', 'tb_dutyplan_roles.roleID', '=', 'tb_dutyplan_events.roleID')
            ->orderBy('tb_dutyplan_events.duty_date')
            ->orderBy('tb_dutyplan_roles.sort_order')
            ->select('tb_dutyplan_events.*')
            ->get();
    }

    /**
     * Meldet, ob einem Termin ein Pflichtgruppen-Mitglied fehlt (Rolle hat
     * eine Pflichtgruppe, aber keine der Zuteilungen ist Mitglied davon).
     */
    public function eventGroupWarning(DutyPlanEvent $event): ?string
    {
        $role = $event->role;

        if (!$role || !$role->requiresGroup()) {
            return null;
        }

        $groupMemberIDs = $role->requiredGroup
            ?->members()
            ->pluck('memberID')
            ->all() ?? [];

        $matches = $event->assignments
            ->filter(fn ($assignment) => $assignment->memberID && in_array($assignment->memberID, $groupMemberIDs, true))
            ->count();

        if ($matches >= $role->required_group_min) {
            return null;
        }

        return 'Mindestens '
            . $role->required_group_min
            . ' Helfer aus "'
            . $role->requiredGroup->name
            . '" fehlt.';
    }

    public function autoAssign(
        DutyPlanAssignmentService $service
    ): void {
        $this->validate([
            'dateFrom' => ['required', 'date'],
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom'],
        ]);

        if (!$this->planId) {
            $this->addError(
                'planId',
                'Bitte zuerst einen Dienstplan auswählen.'
            );

            return;
        }

        $result = $service->assign(
            $this->planId,
            $this->dateFrom,
            $this->dateTo
        );

        session()->flash(
            'success',
            $result['assigned']
            . ' Helfer wurden eingeteilt.'
            . (
            $result['unfilled'] > 0
                ? ' '
                . $result['unfilled']
                . ' Plätze konnten nicht besetzt werden.'
                : ''
            )
        );
    }

    public function weekdayName(int $weekday): string
    {
        return match ($weekday) {
            1 => 'Montag',
            2 => 'Dienstag',
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag',
            6 => 'Samstag',
            7 => 'Sonntag',
        };
    }

    public function generateEvents(
        DutyPlanGeneratorService $generator
    ): void {
        $this->validate();

        if (!$this->planId) {
            $this->addError(
                'planId',
                'Bitte zuerst einen Dienstplan auswählen oder erstellen.'
            );

            return;
        }

        $plan = DutyPlan::findOrFail($this->planId);

        $activeRoles = $plan->roles()->where('active', true)->count();

        if ($activeRoles === 0) {
            session()->flash(
                'error',
                'Für diesen Dienstplan sind noch keine (aktiven) Dienstbezeichnungen angelegt.'
            );

            return;
        }

        $result = $generator->generate(
            $plan,
            $this->dateFrom,
            $this->dateTo
        );

        $message = $result['created']
            . ' Termine erzeugt, '
            . $result['updated']
            . ' aktualisiert.';

        if ($result['skipped_holidays'] > 0) {
            $message .= ' ' . $result['skipped_holidays'] . ' Feiertagstermine wurden ausgelassen.';
        }

        if ($result['removed'] > 0) {
            $message .= ' ' . $result['removed'] . ' verwaiste Termine ohne Einteilung wurden entfernt.';
        }

        if ($result['orphaned'] > 0) {
            $message .= ' ' . $result['orphaned'] . ' verwaiste Termine mit bestehenden Einteilungen wurden NICHT entfernt — bitte prüfen.';
        }

        session()->flash('success', $message);
    }

    public function holidayName($date): ?string
    {
        return app(DutyPlanGeneratorService::class)
            ->getHolidayName(
                Carbon::parse($date)
            );
    }

    public function memberAvailableForEvent(
        int $memberId,
            $date
    ): bool {
        return !\App\Models\DutyPlanAbsence::query()
            ->where('memberID', $memberId)
            ->whereDate('date_from', '<=', $date)
            ->whereDate('date_to', '>=', $date)
            ->exists();
    }

    public function render()
    {

        $volunteers = DutyPlanVolunteer::query()
            ->with('member')
            ->where('planID', $this->planId)
            ->whereNotNull('memberID')
            ->where('active', 1)
            ->get()
            ->filter(fn ($volunteer) =>
                $volunteer->member !== null
                && (bool) $volunteer->member->active
            )
            ->sortBy(fn ($volunteer) =>
                $volunteer->member->surname . ' ' . $volunteer->member->name
            )
            ->values();

        $externalVolunteers = DutyPlanVolunteer::query()
            ->with('externalContact')
            ->where('planID', $this->planId)
            ->whereNotNull('externalContactID')
            ->where('active', true)
            ->whereHas(
                'externalContact',
                fn ($query) => $query->where('active', true)
            )
            ->get()
            ->sortBy(
                fn ($volunteer) =>
                    $volunteer->externalContact->surname
                    . ' '
                    . $volunteer->externalContact->name
            )
            ->values();

        return view('livewire.duty-plan.index', [
            'volunteers' => $volunteers,
            'externalVolunteers' => $externalVolunteers,
        ])->layout('layouts.app', [
            'title' => 'Dienstplan | VEMA',
            'heading' => 'Dienstplan',
        ]);
    }
}
