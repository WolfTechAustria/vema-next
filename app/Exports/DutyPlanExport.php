<?php

namespace App\Exports;

use App\Models\DutyPlanEvent;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Support\Enumerable;

class DutyPlanExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize
{
    public function __construct(
        protected int $planId,
        protected string $dateFrom,
        protected string $dateTo,
        protected ?int $weekday = null
    ) {
    }

    public function collection(): Enumerable
    {
        return DutyPlanEvent::query()
            ->where('planID', $this->planId)
            ->with([
                'assignments.member',
            ])
            ->whereBetween('duty_date', [
                $this->dateFrom,
                $this->dateTo,
            ])
            ->when(
                $this->weekday,
                fn ($query) =>
                $query->whereRaw(
                    'WEEKDAY(duty_date) + 1 = ?',
                    [$this->weekday]
                )
            )
            ->orderBy('duty_date')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Datum',
            'Wochentag',
            'Dienst',
            'Helfer 1',
            'Helfer 2',
            'Helfer 3',
            'Helfer 4',
        ];
    }

    public function map($event): array
    {
        $assignments = $event->assignments
            ->sortBy('slot_no')
            ->values();

        return [
            $event->duty_date->format('d.m.Y'),
            $event->duty_date
                ->locale('de')
                ->translatedFormat('l'),

            $event->duty_name,

            $assignments->get(0)?->member?->full_name ?? '',
            $assignments->get(1)?->member?->full_name ?? '',
            $assignments->get(2)?->member?->full_name ?? '',
            $assignments->get(3)?->member?->full_name ?? '',
        ];
    }
}
