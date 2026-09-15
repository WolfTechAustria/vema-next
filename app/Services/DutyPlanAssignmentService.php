<?php

namespace App\Services;

use App\Models\DutyPlanAbsence;
use App\Models\DutyPlanAssignment;
use App\Models\DutyPlanEvent;
use App\Models\DutyPlanExternalVolunteer;
use App\Models\DutyPlanVolunteer;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DutyPlanAssignmentService
{
    public function assign(
        int $planID,
        string $dateFrom,
        string $dateTo
    ): array {
        $events = DutyPlanEvent::query()
            ->where('planID', $planID)
            ->whereBetween('duty_date', [$dateFrom, $dateTo])
            ->orderBy('duty_date')
            ->get();

        /*
         * Vereinsmitglieder
         */
        $memberVolunteers = DutyPlanVolunteer::query()
            ->with('member')
            ->where('active', true)
            ->whereHas(
                'member',
                fn ($q) => $q->where('active', true)
            )
            ->get();

        /*
         * Externe Helfer
         */
        $externalVolunteers = DutyPlanExternalVolunteer::query()
            ->with('externalContact')
            ->where('active', true)
            ->whereHas(
                'externalContact',
                fn ($q) => $q->where('active', true)
            )
            ->get();

        /*
         * Beide Helferpools in ein einheitliches Format bringen.
         */
        $volunteers = collect();

        foreach ($memberVolunteers as $volunteer) {
            $volunteers->push([
                'key' => 'member:' . $volunteer->memberID,
                'type' => 'member',
                'memberID' => (int) $volunteer->memberID,
                'externalContactID' => null,
                'volunteer' => $volunteer,
            ]);
        }

        foreach ($externalVolunteers as $volunteer) {
            $volunteers->push([
                'key' => 'external:' . $volunteer->externalContactID,
                'type' => 'external',
                'memberID' => null,
                'externalContactID' => (int) $volunteer->externalContactID,
                'volunteer' => $volunteer,
            ]);
        }

        $assigned = 0;
        $unfilled = 0;

        DB::transaction(function () use (
            $planID,
            $events,
            $volunteers,
            $dateFrom,
            $dateTo,
            &$assigned,
            &$unfilled
        ) {
            /*
             * Bestehende Zuweisungen im gewählten Zeitraum löschen.
             */
            DutyPlanAssignment::query()
                ->whereHas(
                    'event',
                    fn ($q) =>
                    $q->where('planID', $planID)
                        ->whereBetween(
                            'duty_date',
                            [$dateFrom, $dateTo]
                        )
                )
                ->delete();

            $serviceCounts = [];
            $lastServiceDates = [];
            $teamCounts = [];

            foreach ($volunteers as $candidate) {
                $serviceCounts[$candidate['key']] = 0;
                $lastServiceDates[$candidate['key']] = null;
            }

            /*
             * Frühere Dienste berücksichtigen.
             */
            $previousAssignments = DutyPlanAssignment::query()
                ->with('event')
                ->whereHas(
                    'event',
                    fn ($q) =>
                    $q->where('planID', $planID)
                        ->where('duty_date', '<', $dateFrom)
                )
                ->get();

            foreach ($previousAssignments as $assignment) {
                $key = $this->assignmentKey($assignment);

                if (!$key) {
                    continue;
                }

                if (!array_key_exists($key, $lastServiceDates)) {
                    continue;
                }

                $currentLastDate = $lastServiceDates[$key];

                if (
                    !$currentLastDate
                    || Carbon::parse($assignment->event->duty_date)
                        ->greaterThan(Carbon::parse($currentLastDate))
                ) {
                    $lastServiceDates[$key] =
                        $assignment->event->duty_date;
                }
            }

            /*
             * Termine durchlaufen.
             */
            foreach ($events as $event) {
                $selected = [];

                for (
                    $slot = 0;
                    $slot < $event->required_helpers;
                    $slot++
                ) {
                    $candidate = $this->pickCandidate(
                        $event,
                        $volunteers,
                        $selected,
                        $serviceCounts,
                        $lastServiceDates,
                        $teamCounts
                    );

                    if (!$candidate) {
                        $unfilled++;
                        continue;
                    }

                    DutyPlanAssignment::create([
                        'eventID' => $event->eventID,

                        'memberID' =>
                            $candidate['memberID'],

                        'externalContactID' =>
                            $candidate['externalContactID'],

                        'slot_no' => $slot + 1,
                    ]);

                    $key = $candidate['key'];

                    /*
                     * Teamstatistik aktualisieren.
                     */
                    foreach ($selected as $otherKey) {
                        $teamKey = $this->teamKey(
                            $key,
                            $otherKey
                        );

                        $teamCounts[$teamKey] =
                            ($teamCounts[$teamKey] ?? 0) + 1;
                    }

                    $selected[] = $key;

                    $serviceCounts[$key]++;

                    $lastServiceDates[$key] =
                        $event->duty_date;

                    $assigned++;
                }
            }
        });

        return [
            'assigned' => $assigned,
            'unfilled' => $unfilled,
        ];
    }

    protected function pickCandidate(
        DutyPlanEvent $event,
        Collection $volunteers,
        array $selected,
        array $serviceCounts,
        array $lastServiceDates,
        array $teamCounts
    ): ?array {
        $weekday = $event->duty_date->isoWeekday();

        $eligible = $volunteers
            ->filter(function ($candidate) use (
                $event,
                $weekday,
                $selected
            ) {
                /*
                 * Nicht doppelt am selben Termin.
                 */
                if (
                    in_array(
                        $candidate['key'],
                        $selected,
                        true
                    )
                ) {
                    return false;
                }

                /*
                 * Wochentags-Verfügbarkeit.
                 */
                if (
                    !$candidate['volunteer']
                        ->isAvailableOnWeekday($weekday)
                ) {
                    return false;
                }

                /*
                 * Abwesenheiten.
                 */
                if (
                    $this->isAbsent(
                        $candidate,
                        $event->duty_date
                    )
                ) {
                    return false;
                }

                return true;
            });

        if ($eligible->isEmpty()) {
            return null;
        }

        return $eligible
            ->map(function ($candidate) use (
                $event,
                $selected,
                $serviceCounts,
                $lastServiceDates,
                $teamCounts
            ) {
                $key = $candidate['key'];

                $serviceCount =
                    $serviceCounts[$key] ?? 0;

                $lastDate =
                    $lastServiceDates[$key] ?? null;

                $daysSinceLast = $lastDate
                    ? Carbon::parse($lastDate)
                        ->diffInDays($event->duty_date)
                    : 9999;

                /*
                 * Wiederholte Teams vermeiden.
                 */
                $teamPenalty = 0;

                foreach ($selected as $otherKey) {
                    $teamKey = $this->teamKey(
                        $key,
                        $otherKey
                    );

                    $teamPenalty +=
                        $teamCounts[$teamKey] ?? 0;
                }

                /*
                 * Zu kurze Abstände stark bestrafen.
                 */
                $consecutivePenalty = 0;

                if ($lastDate) {
                    $last = Carbon::parse($lastDate);

                    if (
                        $last->diffInDays(
                            $event->duty_date
                        ) <= 4
                    ) {
                        $consecutivePenalty = 1000;
                    }
                }

                $score =
                    ($serviceCount * 100)
                    - min($daysSinceLast, 365)
                    + ($teamPenalty * 25)
                    + $consecutivePenalty
                    + random_int(0, 20);

                return [
                    'candidate' => $candidate,
                    'score' => $score,
                ];
            })
            ->sortBy('score')
            ->first()['candidate']
            ?? null;
    }

    protected function isAbsent(
        array $candidate,
        CarbonInterface $date
    ): bool {
        /*
         * Bestehende Abwesenheitstabelle gilt momentan
         * ausschließlich für Mitglieder.
         */
        if ($candidate['type'] !== 'member') {
            return false;
        }

        return DutyPlanAbsence::query()
            ->where(
                'memberID',
                $candidate['memberID']
            )
            ->whereDate(
                'date_from',
                '<=',
                $date->toDateString()
            )
            ->whereDate(
                'date_to',
                '>=',
                $date->toDateString()
            )
            ->exists();
    }

    protected function assignmentKey(
        DutyPlanAssignment $assignment
    ): ?string {
        if ($assignment->memberID) {
            return 'member:' . $assignment->memberID;
        }

        if ($assignment->externalContactID) {
            return 'external:'
                . $assignment->externalContactID;
        }

        return null;
    }

    protected function teamKey(
        string $a,
        string $b
    ): string {
        $keys = [$a, $b];

        sort($keys);

        return implode('|', $keys);
    }
}
