<?php

namespace App\Services;

use App\Models\DutyPlanAbsence;
use App\Models\DutyPlanAssignment;
use App\Models\DutyPlanEvent;
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
            ->where('tb_dutyplan_events.planID', $planID)
            ->whereBetween('tb_dutyplan_events.duty_date', [$dateFrom, $dateTo])
            ->with('role')
            ->join('tb_dutyplan_roles', 'tb_dutyplan_roles.roleID', '=', 'tb_dutyplan_events.roleID')
            ->orderBy('tb_dutyplan_events.duty_date')
            ->orderBy('tb_dutyplan_roles.sort_order')
            ->select('tb_dutyplan_events.*')
            ->get()
            ->groupBy(fn (DutyPlanEvent $event) => $event->duty_date->toDateString());

        /*
         * Plan-eigene Helferliste (Mitglieder + externe Helfer).
         */
        $planVolunteers = DutyPlanVolunteer::query()
            ->with(['member', 'externalContact'])
            ->where('planID', $planID)
            ->where('active', true)
            ->where(function ($query) {
                $query
                    ->whereHas('member', fn ($q) => $q->where('active', true))
                    ->orWhereHas('externalContact', fn ($q) => $q->where('active', true));
            })
            ->get();

        $memberIDs = $planVolunteers->pluck('memberID')->filter()->all();
        $externalContactIDs = $planVolunteers->pluck('externalContactID')->filter()->all();

        $skillsByMember = DB::table('tb_skill_members')
            ->whereIn('memberID', $memberIDs ?: [0])
            ->get()
            ->groupBy('memberID')
            ->map(fn ($rows) => $rows->pluck('skillID')->map('intval')->all());

        $skillsByExternal = DB::table('tb_skill_external_contacts')
            ->whereIn('externalContactID', $externalContactIDs ?: [0])
            ->get()
            ->groupBy('externalContactID')
            ->map(fn ($rows) => $rows->pluck('skillID')->map('intval')->all());

        $groupsByMember = DB::table('tb_recipient_group_members')
            ->whereIn('memberID', $memberIDs ?: [0])
            ->get()
            ->groupBy('memberID')
            ->map(fn ($rows) => $rows->pluck('groupID')->map('intval')->all());

        $groupsByExternal = DB::table('tb_recipient_group_external_contacts')
            ->whereIn('externalContactID', $externalContactIDs ?: [0])
            ->get()
            ->groupBy('externalContactID')
            ->map(fn ($rows) => $rows->pluck('groupID')->map('intval')->all());

        $volunteers = collect();

        foreach ($planVolunteers as $volunteer) {
            if ($volunteer->is_external) {
                $volunteers->push([
                    'key' => $volunteer->volunteer_key,
                    'type' => 'external',
                    'memberID' => null,
                    'externalContactID' => (int) $volunteer->externalContactID,
                    'volunteer' => $volunteer,
                    'skillIDs' => $skillsByExternal[$volunteer->externalContactID] ?? [],
                    'groupIDs' => $groupsByExternal[$volunteer->externalContactID] ?? [],
                ]);
            } else {
                $volunteers->push([
                    'key' => $volunteer->volunteer_key,
                    'type' => 'member',
                    'memberID' => (int) $volunteer->memberID,
                    'externalContactID' => null,
                    'volunteer' => $volunteer,
                    'skillIDs' => $skillsByMember[$volunteer->memberID] ?? [],
                    'groupIDs' => $groupsByMember[$volunteer->memberID] ?? [],
                ]);
            }
        }

        $assigned = 0;
        $unfilled = 0;
        $groupViolations = [];

        DB::transaction(function () use (
            $planID,
            $events,
            $volunteers,
            $dateFrom,
            $dateTo,
            &$assigned,
            &$unfilled,
            &$groupViolations
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
             * Termine durchlaufen, gruppiert nach Datum: eine Person darf
             * nicht gleichzeitig zwei Rollen desselben Tages besetzen
             * (z. B. nicht Bar UND Auswertung am selben Tag).
             */
            foreach ($events as $dateKey => $eventsOfDate) {
                $selectedOnDate = [];

                /*
                 * Rollen mit Pflicht-Fähigkeit zuerst (dafür gibt es KEINEN
                 * Ausweich-Kandidaten — wird der einzige Kandidat vorher von
                 * einer anderen Rolle verbraucht, bleibt der Slot leer),
                 * danach Rollen mit Pflichtgruppe (die im Notfall auf den
                 * freien Pool ausweichen können), zuletzt unbeschränkte
                 * Rollen. Sonst könnte z. B. "Bar" den einzigen passenden
                 * Kandidaten für "Auswertung"/"Standaufsicht" desselben
                 * Tages vorweg verbrauchen.
                 */
                $eventsOfDate = $eventsOfDate->sortByDesc(
                    fn (DutyPlanEvent $event) =>
                        ((int) ($event->role?->requiredSkillID !== null) * 2)
                        + (int) ($event->role?->requiredGroupID !== null)
                );

                foreach ($eventsOfDate as $event) {
                    $role = $event->role;

                    $groupQuota = ($role && $role->requiredGroupID)
                        ? min(max(1, $role->required_group_min), $event->required_helpers)
                        : 0;

                    $groupFilled = 0;

                    for (
                        $slot = 0;
                        $slot < $event->required_helpers;
                        $slot++
                    ) {
                        $remainingSlots = $event->required_helpers - $slot;
                        $mustBeGroupMember = $groupQuota > 0
                            && ($groupQuota - $groupFilled) >= $remainingSlots;

                        $candidate = $this->pickCandidate(
                            $event,
                            $volunteers,
                            $selectedOnDate,
                            $serviceCounts,
                            $lastServiceDates,
                            $teamCounts,
                            $mustBeGroupMember ? $role->requiredGroupID : null
                        );

                        if (!$candidate && $mustBeGroupMember) {
                            // Niemand aus der Pflichtgruppe verfügbar — Slot
                            // trotzdem aus dem regulären Pool besetzen, aber
                            // als Verstoß melden (nie leer lassen).
                            $candidate = $this->pickCandidate(
                                $event,
                                $volunteers,
                                $selectedOnDate,
                                $serviceCounts,
                                $lastServiceDates,
                                $teamCounts,
                                null
                            );

                            if ($candidate) {
                                $groupViolations[] = [
                                    'date' => $event->duty_date->toDateString(),
                                    'duty_name' => $event->duty_name,
                                    'group' => $role->requiredGroup?->name,
                                ];
                            }
                        }

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
                        foreach ($selectedOnDate as $otherKey) {
                            $teamKey = $this->teamKey(
                                $key,
                                $otherKey
                            );

                            $teamCounts[$teamKey] =
                                ($teamCounts[$teamKey] ?? 0) + 1;
                        }

                        $selectedOnDate[] = $key;

                        if (
                            $groupQuota > 0
                            && in_array($role->requiredGroupID, $candidate['groupIDs'], true)
                        ) {
                            $groupFilled++;
                        }

                        $serviceCounts[$key]++;

                        $lastServiceDates[$key] =
                            $event->duty_date;

                        $assigned++;
                    }
                }
            }
        });

        return [
            'assigned' => $assigned,
            'unfilled' => $unfilled,
            'group_violations' => $groupViolations,
        ];
    }

    protected function pickCandidate(
        DutyPlanEvent $event,
        Collection $volunteers,
        array $selected,
        array $serviceCounts,
        array $lastServiceDates,
        array $teamCounts,
        ?int $requireGroupID = null
    ): ?array {
        $weekday = $event->duty_date->isoWeekday();
        $requiredSkillID = $event->role?->requiredSkillID;

        $eligible = $volunteers
            ->filter(function ($candidate) use (
                $event,
                $weekday,
                $selected,
                $requiredSkillID,
                $requireGroupID
            ) {
                /*
                 * Nicht doppelt am selben Tag (auch nicht in einer anderen
                 * Rolle desselben Tages, z. B. Bar UND Auswertung).
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

                /*
                 * Pflicht-Fähigkeit der Dienstbezeichnung.
                 */
                if (
                    $requiredSkillID
                    && !in_array((int) $requiredSkillID, $candidate['skillIDs'], true)
                ) {
                    return false;
                }

                /*
                 * Für den reservierten Pflichtgruppen-Slot: nur Mitglieder
                 * dieser Gruppe kommen in Frage.
                 */
                if (
                    $requireGroupID
                    && !in_array($requireGroupID, $candidate['groupIDs'], true)
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
