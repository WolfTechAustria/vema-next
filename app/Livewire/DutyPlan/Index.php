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


class Index extends Component
{
    public ?int $deletePlanId = null;

    public ?int $planId = null;

    public string $planName = '';

    public bool $showCreatePlan = false;
    public string $dateFrom = '';
    public string $dateTo = '';

    public string $exportWeekday = 'all';
    public bool $excludeHolidays = true;

    public array $weekdayRules = [
        1 => [
            'active' => true,
            'name' => 'Training',
            'required_people' => 1,
        ],
        2 => [
            'active' => false,
            'name' => 'Dienst',
            'required_people' => 1,
        ],
        3 => [
            'active' => false,
            'name' => 'Dienst',
            'required_people' => 1,
        ],
        4 => [
            'active' => false,
            'name' => 'Dienst',
            'required_people' => 1,
        ],
        5 => [
            'active' => true,
            'name' => 'Saisonabend',
            'required_people' => 2,
        ],
        6 => [
            'active' => false,
            'name' => 'Dienst',
            'required_people' => 1,
        ],
        7 => [
            'active' => false,
            'name' => 'Dienst',
            'required_people' => 1,
        ],
    ];

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

        $plan = DutyPlan::create([
            'name' => $validated['planName'],

            'date_from' => $validated['dateFrom'],
            'date_to' => $validated['dateTo'],

            'exclude_holidays' =>
                $validated['excludeHolidays'],
        ]);

        $this->planId = $plan->planID;

        $this->showCreatePlan = false;

        session()->flash(
            'success',
            'Dienstplan wurde angelegt.'
        );
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

        $this->selectPlan($copy->planID);

        session()->flash(
            'success',
            'Dienstplan wurde dupliziert. Termine und Helferzuweisungen wurden nicht übernommen.'
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
        ?int $memberId
    ): void {
        $assignment = DutyPlanAssignment::query()
            ->where('eventID', $eventId)
            ->where('slot_no', $slotNo)
            ->first();

        $alreadyAssigned = DutyPlanAssignment::query()
            ->where('eventID', $eventId)
            ->where('memberID', $memberId)
            ->where('slot_no', '!=', $slotNo)
            ->exists();

        if ($alreadyAssigned) {
            session()->flash(
                'error',
                'Dieses Mitglied ist bei diesem Termin bereits eingeteilt.'
            );

            return;
        }

        if (!$memberId) {
            $assignment?->delete();

            return;
        }

        DutyPlanAssignment::updateOrCreate(
            [
                'eventID' => $eventId,
                'slot_no' => $slotNo,
            ],
            [
                'memberID' => $memberId,
            ]
        );
    }


    protected function rules(): array
    {
        return [
            'dateFrom' => ['required', 'date'],
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom'],

            'weekdayRules' => ['array'],

            'weekdayRules.*.active' => ['boolean'],
            'weekdayRules.*.name' => ['required', 'string', 'max:100'],
            'weekdayRules.*.required_people' => [
                'required',
                'integer',
                'min:1',
                'max:20',
            ],

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
                'assignments.member',
            ])
            ->where('planID', $this->planId)
            ->whereBetween('duty_date', [
                $this->dateFrom,
                $this->dateTo,
            ])
            ->orderBy('duty_date')
            ->get();
    }

    public function autoAssign(
        DutyPlanAssignmentService $service
    ): void {
        $this->validate([
            'dateFrom' => ['required', 'date'],
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom'],
        ]);

        $result = $service->assign(
            $this->planId
        );

        session()->flash(
            'success',
            $result['assigned']
            . ' Helfer wurden eingeteilt.'
            . (
            $result['unfilled'] > 0
                ? ' ' . $result['unfilled'] . ' Plätze konnten nicht besetzt werden.'
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

        $activeRules = collect($this->weekdayRules)
            ->filter(fn ($rule) => $rule['active'])
            ->count();

        if ($activeRules === 0) {
            $this->addError(
                'weekdayRules',
                'Mindestens ein Wochentag muss aktiviert sein.'
            );

            return;
        }

        $result = $generator->generate(
            $this->planId,
            $this->dateFrom,
            $this->dateTo,
            $this->weekdayRules,
            $this->excludeHolidays
        );

        session()->flash(
            'success',
            $result['created']
            . ' Termine wurden erzeugt.'
            . (
            $result['skipped_holidays'] > 0
                ? ' '
                . $result['skipped_holidays']
                . ' Feiertagstermine wurden ausgelassen.'
                : ''
            )
        );
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

        return view('livewire.duty-plan.index', [
            'volunteers' => $volunteers,
        ])->layout('layouts.app', [
            'title' => 'Dienstplan | VEMA',
            'heading' => 'Dienstplan',
        ]);
    }
}
