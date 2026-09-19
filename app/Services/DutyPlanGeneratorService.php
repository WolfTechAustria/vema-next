<?php

namespace App\Services;

use App\Models\DutyPlan;
use App\Models\DutyPlanEvent;
use App\Models\DutyPlanRole;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class DutyPlanGeneratorService
{
    public function generate(
        DutyPlan $plan,
        string $dateFrom,
        string $dateTo
    ): array {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->startOfDay();

        $roles = DutyPlanRole::query()
            ->where('planID', $plan->planID)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('roleID')
            ->get();

        $created = 0;
        $updated = 0;
        $skippedHolidays = 0;

        $touchedEventIDs = [];

        DB::transaction(function () use (
            $plan,
            $from,
            $to,
            $roles,
            &$created,
            &$updated,
            &$skippedHolidays,
            &$touchedEventIDs
        ) {
            foreach (CarbonPeriod::create($from, $to) as $date) {
                $weekday = $date->isoWeekday();

                if (
                    $plan->exclude_holidays
                    && $this->isAustrianHoliday($date)
                ) {
                    $skippedHolidays++;

                    continue;
                }

                foreach ($roles as $role) {
                    if (!$role->isActiveOnWeekday($weekday)) {
                        continue;
                    }

                    $event = DutyPlanEvent::query()
                        ->where('planID', $plan->planID)
                        ->whereDate('duty_date', $date->toDateString())
                        ->where('roleID', $role->roleID)
                        ->first();

                    if ($event) {
                        $event->update([
                            'duty_name' => $role->name,
                            'required_helpers' => $role->required_helpers,
                            'start_time' => $role->start_time,
                            'end_time' => $role->end_time,
                        ]);

                        $touchedEventIDs[] = $event->eventID;

                        $updated++;

                        continue;
                    }

                    $event = DutyPlanEvent::create([
                        'planID' => $plan->planID,
                        'roleID' => $role->roleID,
                        'duty_date' => $date->toDateString(),
                        'duty_name' => $role->name,
                        'required_helpers' => $role->required_helpers,
                        'start_time' => $role->start_time,
                        'end_time' => $role->end_time,
                    ]);

                    $touchedEventIDs[] = $event->eventID;

                    $created++;
                }
            }
        });

        [$removed, $orphaned] = $this->pruneOrphanedEvents(
            $plan,
            $from,
            $to,
            $roles,
            $touchedEventIDs
        );

        return [
            'created' => $created,
            'updated' => $updated,
            'removed' => $removed,
            'orphaned' => $orphaned,
            'skipped_holidays' => $skippedHolidays,
        ];
    }

    /**
     * Entfernt Termine im Zeitraum, deren Rolle mittlerweile inaktiv ist oder
     * an diesem Wochentag nicht mehr aktiv ist (oder die jetzt ein
     * ausgelassener Feiertag sind) — aber NUR, wenn noch niemand eingeteilt
     * ist. Termine mit bestehenden Einteilungen werden nie automatisch
     * gelöscht, sondern nur als "zu prüfen" gemeldet.
     *
     * @return array{0: int, 1: int} [removedCount, orphanedWithAssignmentsCount]
     */
    protected function pruneOrphanedEvents(
        DutyPlan $plan,
        Carbon $from,
        Carbon $to,
        \Illuminate\Support\Collection $activeRoles,
        array $touchedEventIDs
    ): array {
        $candidates = DutyPlanEvent::query()
            ->with('assignments')
            ->where('planID', $plan->planID)
            ->whereBetween('duty_date', [
                $from->toDateString(),
                $to->toDateString(),
            ])
            ->whereNotIn('eventID', $touchedEventIDs ?: [0])
            ->get();

        $removed = 0;
        $orphaned = 0;

        foreach ($candidates as $event) {
            if ($event->assignments->isNotEmpty()) {
                $orphaned++;

                continue;
            }

            $event->delete();

            $removed++;
        }

        return [$removed, $orphaned];
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
