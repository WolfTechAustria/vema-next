<?php

namespace App\Services;

use App\Enums\EventResponseStatus;
use App\Models\ClubEvent;
use App\Models\ClubEventAttendance;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Anwesenheit bei Vereinsterminen erfassen und auswerten.
 */
class EventAttendance
{
    public const ROLE = 'anwesenheit';

    /**
     * Admins und Funktionäre mit der Rolle „Anwesenheit“.
     */
    public static function canManage(): bool
    {
        return (bool) auth()->guard('web')->user()?->hasAnyRole(['admin', self::ROLE]);
    }

    /**
     * Anwesenheit lässt sich für eigene Termine erfassen, sobald ihr Tag erreicht ist.
     */
    public static function canRecord(ClubEvent $event): bool
    {
        return ! $event->isExternal() && $event->starts_at->lte(now()->endOfDay());
    }

    /**
     * Mitglieder in der Anwesenheitsliste: bei eingeschränkten Terminen die
     * ausgewählten, sonst alle aktiven – dazu alle bereits erfassten.
     *
     * @return Collection<int, Member>
     */
    public function eligibleMembers(ClubEvent $event): Collection
    {
        $recordedIDs = $event->attendances()->pluck('memberID')->all();
        $invitedIDs = $event->members()->pluck('tb_members.memberID')->all();

        return Member::query()
            ->where(function (Builder $query) use ($invitedIDs, $recordedIDs) {
                if ($invitedIDs !== []) {
                    $query->whereIn('memberID', $invitedIDs);
                } else {
                    $query->where('active', true);
                }

                $query->orWhereIn('memberID', $recordedIDs);
            })
            ->orderBy('surname')
            ->orderBy('name')
            ->get();
    }

    /**
     * Vorauswahl: gespeicherte Anwesenheit oder – beim ersten Erfassen – die Zusagen.
     *
     * @return list<int>
     */
    public function presentMemberIDs(ClubEvent $event): array
    {
        $query = $event->attendance_taken_at
            ? $event->attendances()->where('present', true)
            : $event->responses()->where('status', EventResponseStatus::Attending);

        return $query->pluck('memberID')->map(fn ($memberID) => (int) $memberID)->values()->all();
    }

    /**
     * Speichert die Anwesenheit aller Mitglieder der Liste.
     *
     * @param  list<int|string>  $presentMemberIDs
     * @return array{present: int, total: int}
     */
    public function record(ClubEvent $event, array $presentMemberIDs): array
    {
        $memberIDs = $this->eligibleMembers($event)->map(fn (Member $member) => (int) $member->memberID)->all();
        $present = array_values(array_intersect($memberIDs, array_map('intval', $presentMemberIDs)));

        DB::transaction(function () use ($event, $memberIDs, $present) {
            foreach ($memberIDs as $memberID) {
                ClubEventAttendance::query()->updateOrCreate(
                    ['eventID' => $event->eventID, 'memberID' => $memberID],
                    ['present' => in_array($memberID, $present, true)],
                );
            }

            $event->forceFill(['attendance_taken_at' => $event->attendance_taken_at ?? now()])->save();
        });

        return ['present' => count($present), 'total' => count($memberIDs)];
    }

    /**
     * Anwesenheit eines Mitglieds im Kalenderjahr.
     *
     * @return array{present: int, total: int, percent: int|null}
     */
    public function quoteFor(Member $member, int $year): array
    {
        $row = $this->yearQuery($year)
            ->where('memberID', $member->memberID)
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(CASE WHEN present THEN 1 ELSE 0 END), 0) as present_count')
            ->toBase()
            ->first();

        return self::quote((int) ($row->present_count ?? 0), (int) ($row->total ?? 0));
    }

    /**
     * Erfasste Termine eines Mitglieds im Kalenderjahr, neueste zuerst.
     *
     * @return Collection<int, ClubEventAttendance>
     */
    public function recordsFor(Member $member, int $year): Collection
    {
        return $this->yearQuery($year)
            ->where('memberID', $member->memberID)
            ->with('event')
            ->get()
            ->sortByDesc(fn (ClubEventAttendance $attendance) => $attendance->event->starts_at)
            ->values();
    }

    /**
     * Anwesenheit aller erfassten Mitglieder im Kalenderjahr, beste Quote zuerst.
     *
     * @return Collection<int, array{member: Member, present: int, total: int, percent: int|null}>
     */
    public function statistics(int $year): Collection
    {
        $rows = $this->yearQuery($year)
            ->groupBy('memberID')
            ->selectRaw('memberID, COUNT(*) as total, COALESCE(SUM(CASE WHEN present THEN 1 ELSE 0 END), 0) as present_count')
            ->toBase()
            ->get()
            ->keyBy('memberID');

        return Member::query()
            ->whereIn('memberID', $rows->keys()->all())
            ->orderBy('surname')
            ->orderBy('name')
            ->get()
            ->map(fn (Member $member) => ['member' => $member] + self::quote(
                (int) $rows[$member->memberID]->present_count,
                (int) $rows[$member->memberID]->total,
            ))
            ->sortByDesc('percent')
            ->values();
    }

    /**
     * Kalenderjahre mit erfasster Anwesenheit, neuestes zuerst.
     *
     * @return list<int>
     */
    public function years(): array
    {
        return ClubEvent::query()
            ->whereNotNull('attendance_taken_at')
            ->pluck('starts_at')
            ->map(fn ($startsAt) => (int) $startsAt->year)
            ->push((int) now()->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * @return Builder<ClubEventAttendance>
     */
    private function yearQuery(int $year): Builder
    {
        return ClubEventAttendance::query()
            ->whereIn('eventID', ClubEvent::query()
                ->whereNotNull('attendance_taken_at')
                ->whereBetween('starts_at', [now()->setDate($year, 1, 1)->startOfDay(), now()->setDate($year, 12, 31)->endOfDay()])
                ->select('eventID'));
    }

    /**
     * @return array{present: int, total: int, percent: int|null}
     */
    private static function quote(int $present, int $total): array
    {
        return [
            'present' => $present,
            'total' => $total,
            'percent' => $total > 0 ? (int) round($present / $total * 100) : null,
        ];
    }
}
