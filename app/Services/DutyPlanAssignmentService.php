<?php

namespace App\Services;

use App\Models\DutyPlanAbsence;
use App\Models\DutyPlanAssignment;
use App\Models\DutyPlanEvent;
use App\Models\DutyPlanVolunteer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonInterface;

class DutyPlanAssignmentService
{
    public function assign(int $planID, string $dateFrom, string $dateTo): array
    {
        $events = DutyPlanEvent::query()
            ->where('planID', $planID)
            ->whereBetween('duty_date', [$dateFrom, $dateTo])
            ->orderBy('duty_date')
            ->get();

        $volunteers = DutyPlanVolunteer::query()
            ->with('member')
            ->where('active', true)
            ->whereHas('member', fn ($q) => $q->where('active', true))
            ->get();

        $assigned = 0;
        $unfilled = 0;

        DB::transaction(function () use (
            $events,
            $volunteers,
            $dateFrom,
            $dateTo,
            &$assigned,
            &$unfilled
        ) {
            DutyPlanAssignment::query()
                ->whereHas('event', fn ($q) =>
                $q->whereBetween('duty_date', [$dateFrom, $dateTo])
                )
                ->delete();

            $serviceCounts = [];
            $lastServiceDates = [];
            $teamCounts = [];

            foreach ($volunteers as $volunteer) {
                $serviceCounts[$volunteer->memberID] = 0;
                $lastServiceDates[$volunteer->memberID] = null;
            }

            $previousAssignments = DutyPlanAssignment::query()
                ->with('event')
                ->whereHas('event', fn ($q) =>
                $q->where('duty_date', '<', $dateFrom)
                )
                ->get()
                ->groupBy('memberID');

            foreach ($previousAssignments as $memberId => $assignments) {
                $last = $assignments
                    ->sortByDesc(fn ($a) => $a->event->duty_date)
                    ->first();

                if ($last?->event) {
                    $lastServiceDates[$memberId] = $last->event->duty_date;
                }
            }

            foreach ($events as $event) {
                $selected = [];

                for ($slot = 0; $slot < $event->required_helpers; $slot++) {
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
                        'memberID' => $candidate->memberID,
                        'slot_no' => $slot + 1,
                    ]);

                    $selected[] = $candidate->memberID;
                    $serviceCounts[$candidate->memberID]++;
                    $lastServiceDates[$candidate->memberID] = $event->duty_date;

                    foreach ($selected as $otherMemberId) {
                        if ($otherMemberId === $candidate->memberID) {
                            continue;
                        }

                        $key = $this->teamKey(
                            $candidate->memberID,
                            $otherMemberId
                        );

                        $teamCounts[$key] = ($teamCounts[$key] ?? 0) + 1;
                    }

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
    ): ?DutyPlanVolunteer {
        $weekday = $event->duty_date->isoWeekday();

        $eligible = $volunteers
            ->filter(function ($volunteer) use (
                $event,
                $weekday,
                $selected
            ) {
                if (in_array($volunteer->memberID, $selected, true)) {
                    return false;
                }

                if (!$volunteer->isAvailableOnWeekday($weekday)) {
                    return false;
                }

                return !$this->isAbsent(
                    $volunteer->memberID,
                    $event->duty_date
                );
            });

        if ($eligible->isEmpty()) {
            return null;
        }

        return $eligible
            ->map(function ($volunteer) use (
                $event,
                $selected,
                $serviceCounts,
                $lastServiceDates,
                $teamCounts
            ) {
                $memberId = $volunteer->memberID;

                $serviceCount = $serviceCounts[$memberId] ?? 0;

                $lastDate = $lastServiceDates[$memberId] ?? null;

                $daysSinceLast = $lastDate
                    ? Carbon::parse($lastDate)->diffInDays($event->duty_date)
                    : 9999;

                $teamPenalty = 0;

                foreach ($selected as $otherMemberId) {
                    $key = $this->teamKey($memberId, $otherMemberId);

                    $teamPenalty += $teamCounts[$key] ?? 0;
                }

                $consecutivePenalty = 0;

                if ($lastDate) {
                    $last = Carbon::parse($lastDate);

                    if ($last->diffInDays($event->duty_date) <= 4) {
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
                    'volunteer' => $volunteer,
                    'score' => $score,
                ];
            })
            ->sortBy('score')
            ->first()['volunteer'] ?? null;
    }

    protected function isAbsent(
        int $memberId,
        CarbonInterface $date
    ): bool
    {
        return DutyPlanAbsence::query()
            ->where('memberID', $memberId)
            ->whereDate('date_from', '<=', $date->toDateString())
            ->whereDate('date_to', '>=', $date->toDateString())
            ->exists();
    }

    protected function teamKey(int $a, int $b): string
    {
        $ids = [$a, $b];

        sort($ids);

        return implode(':', $ids);
    }
}
