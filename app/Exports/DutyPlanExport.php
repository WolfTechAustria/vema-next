<?php

namespace App\Exports;

use App\Models\DutyPlanEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DutyPlanExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize
{
    protected const MAX_HELPER_COLUMNS = 8;

    protected ?Collection $cachedCollection = null;

    public function __construct(
        protected int $planId,
        protected string $dateFrom,
        protected string $dateTo,
        protected ?int $weekday = null,
        protected ?int $roleId = null
    ) {
    }

    public function collection(): Enumerable
    {
        if ($this->cachedCollection) {
            return $this->cachedCollection;
        }

        return $this->cachedCollection = DutyPlanEvent::query()
            ->where('tb_dutyplan_events.planID', $this->planId)
            ->with([
                'role',
                'assignments.member',
                'assignments.externalContact',
            ])
            ->whereBetween('tb_dutyplan_events.duty_date', [
                $this->dateFrom,
                $this->dateTo,
            ])
            ->when(
                $this->weekday,
                fn ($query) =>
                $query->whereRaw(
                    'WEEKDAY(tb_dutyplan_events.duty_date) + 1 = ?',
                    [$this->weekday]
                )
            )
            ->when(
                $this->roleId,
                fn ($query) => $query->where('tb_dutyplan_events.roleID', $this->roleId)
            )
            ->join('tb_dutyplan_roles', 'tb_dutyplan_roles.roleID', '=', 'tb_dutyplan_events.roleID')
            ->orderBy('tb_dutyplan_events.duty_date')
            ->orderBy('tb_dutyplan_roles.sort_order')
            ->select('tb_dutyplan_events.*')
            ->get();
    }

    protected function maxHelperColumns(): int
    {
        return min(
            self::MAX_HELPER_COLUMNS,
            max(1, $this->collection()->max('required_helpers') ?? 1)
        );
    }

    public function headings(): array
    {
        return array_merge(
            ['Datum', 'Wochentag', 'Dienst'],
            array_map(
                fn ($i) => 'Helfer ' . $i,
                range(1, $this->maxHelperColumns())
            )
        );
    }

    public function map($event): array
    {
        $assignments = $event->assignments
            ->sortBy('slot_no')
            ->values();

        $helperCells = [];

        for ($slot = 0; $slot < $this->maxHelperColumns(); $slot++) {
            $helperCells[] = $this->helperName($assignments->get($slot));
        }

        return array_merge(
            [
                $event->duty_date->format('d.m.Y'),

                $event->duty_date
                    ->locale('de')
                    ->translatedFormat('l'),

                $event->duty_name,
            ],
            $helperCells
        );
    }

    protected function helperName($assignment): string
    {
        if (!$assignment) {
            return '';
        }

        if ($assignment->member) {
            return $assignment->member->full_name;
        }

        if ($assignment->externalContact) {
            return $assignment->externalContact->full_name;
        }

        return '';
    }
}
