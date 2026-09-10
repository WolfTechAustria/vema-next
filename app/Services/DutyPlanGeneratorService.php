<?php

namespace App\Services;

use App\Models\DutyPlanEvent;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class DutyPlanGeneratorService
{
    public function generate(
        int $planId,
        string $dateFrom,
        string $dateTo,
        array $weekdayRules,
        bool $excludeHolidays = true
    ): array {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->startOfDay();

        $created = 0;
        $skippedHolidays = 0;

        DB::transaction(function () use (
            $from,
            $to,
            $weekdayRules,
            $excludeHolidays,
            &$created,
            &$skippedHolidays
        ) {
            foreach (CarbonPeriod::create($from, $to) as $date) {
                $weekday = $date->isoWeekday();

                $rule = $weekdayRules[$weekday] ?? null;

                if (!$rule || empty($rule['active'])) {
                    continue;
                }

                if (
                    $excludeHolidays &&
                    $this->isAustrianHoliday($date)
                ) {
                    $skippedHolidays++;

                    continue;
                }

                $dutyType = 'weekday_' . $weekday;

                $event = DutyPlanEvent::query()
                    ->where('planID', $planId)
                    ->whereDate('duty_date', $date->toDateString())
                    ->where('duty_type', $dutyType)
                    ->first();

                if ($event) {
                    $event->update([
                        'duty_name' => $rule['name'],
                        'required_helpers' => (int) $rule['required_people'],
                    ]);

                    continue;
                }

                DutyPlanEvent::create([
                    'planID' => $planId,
                    'duty_date' => $date->toDateString(),
                    'duty_type' => $dutyType,
                    'duty_name' => $rule['name'],
                    'required_helpers' => (int) $rule['required_people'],
                ]);

                $created++;
            }
        });

        return [
            'created' => $created,
            'skipped_holidays' => $skippedHolidays,
        ];
    }

    public function isAustrianHoliday(Carbon $date): bool
    {
        $year = $date->year;

        $fixedHolidays = [
            "$year-01-01" => 'Neujahr',
            "$year-01-06" => 'Heilige Drei Könige',
            "$year-05-01" => 'Staatsfeiertag',
            "$year-08-15" => 'Mariä Himmelfahrt',
            "$year-10-26" => 'Nationalfeiertag',
            "$year-11-01" => 'Allerheiligen',
            "$year-12-08" => 'Mariä Empfängnis',
            "$year-12-25" => 'Christtag',
            "$year-12-26" => 'Stefanitag',
        ];

        if (isset($fixedHolidays[$date->toDateString()])) {
            return true;
        }

        $easterSunday = Carbon::create(
            $year,
            3,
            21
        )->addDays(easter_days($year));

        $movableHolidays = [
            $easterSunday->copy()->addDay(),
            $easterSunday->copy()->addDays(39),
            $easterSunday->copy()->addDays(50),
            $easterSunday->copy()->addDays(60),
        ];

        return collect($movableHolidays)
            ->contains(
                fn (Carbon $holiday) =>
                $holiday->isSameDay($date)
            );
    }

    public function getHolidayName(Carbon $date): ?string
    {
        $year = $date->year;

        $fixedHolidays = [
            "$year-01-01" => 'Neujahr',
            "$year-01-06" => 'Heilige Drei Könige',
            "$year-05-01" => 'Staatsfeiertag',
            "$year-08-15" => 'Mariä Himmelfahrt',
            "$year-10-26" => 'Nationalfeiertag',
            "$year-11-01" => 'Allerheiligen',
            "$year-12-08" => 'Mariä Empfängnis',
            "$year-12-25" => 'Christtag',
            "$year-12-26" => 'Stefanitag',
        ];

        if (isset($fixedHolidays[$date->toDateString()])) {
            return $fixedHolidays[$date->toDateString()];
        }

        $easterSunday = Carbon::create(
            $year,
            3,
            21
        )->addDays(easter_days($year));

        $movable = [
            $easterSunday->copy()->addDay()->toDateString()
            => 'Ostermontag',

            $easterSunday->copy()->addDays(39)->toDateString()
            => 'Christi Himmelfahrt',

            $easterSunday->copy()->addDays(50)->toDateString()
            => 'Pfingstmontag',

            $easterSunday->copy()->addDays(60)->toDateString()
            => 'Fronleichnam',
        ];

        return $movable[$date->toDateString()] ?? null;
    }
}
