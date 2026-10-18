<?php

namespace App\Services;

use App\Models\ClubEvent;
use App\Models\ClubEventSeries;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Generator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Legt Serientermine als einzelne Termine in tb_events an und hält sie bei
 * Änderungen „diesen und alle folgenden“ synchron. Termine, deren Datum
 * gleich bleibt, werden aktualisiert (Zu-/Absagen bleiben erhalten).
 */
class ClubEventSeriesService
{
    public const MAX_OCCURRENCES = 150;

    /**
     * Tage der Serie ab $start (inklusive), ohne ausgeschlossene Tage.
     * Liefert höchstens MAX_OCCURRENCES + 1 Tage, damit zu lange Serien
     * erkannt werden können.
     *
     * @param  array{frequency: string, interval: int, weekdays?: array<int, int>|null, month_week?: int|null, until?: string|null, count?: int|null, excluded_dates?: array<int, string>}  $rule
     * @return array<int, CarbonImmutable>
     */
    public function occurrenceDates(CarbonInterface $start, array $rule): array
    {
        $first = CarbonImmutable::parse($start->format('Y-m-d'));
        $until = ! empty($rule['until']) ? CarbonImmutable::parse($rule['until'])->endOfDay() : null;
        $count = ! empty($rule['count']) ? (int) $rule['count'] : null;
        $excluded = array_flip($rule['excluded_dates'] ?? []);

        $dates = [];
        $generated = 0;

        foreach ($this->candidateDates($first, $rule) as $date) {
            if ($date->lt($first)) {
                continue;
            }

            if (($until && $date->gt($until)) || ($count !== null && $generated >= $count)) {
                break;
            }

            $generated++;

            if (! isset($excluded[$date->format('Y-m-d')])) {
                $dates[] = $date;
            }

            if (count($dates) > self::MAX_OCCURRENCES) {
                break;
            }
        }

        return $dates;
    }

    /**
     * Neue Serie samt allen Terminen anlegen.
     *
     * @param  array<string, mixed>  $attributes  Felder des ersten Termins inkl. starts_at/ends_at
     * @param  array{frequency: string, interval: int, weekdays?: array<int, int>|null, month_week?: int|null, until?: string|null, count?: int|null}  $rule
     * @param  array<int, int>  $memberIDs
     * @return array{series: ClubEventSeries, created: int, updated: int, deleted: int}
     */
    public function create(array $attributes, array $rule, array $memberIDs): array
    {
        return DB::transaction(function () use ($attributes, $rule, $memberIDs) {
            $series = ClubEventSeries::create($this->seriesAttributes($rule));

            return ['series' => $series] + $this->syncOccurrences($series, $attributes, collect(), $memberIDs);
        });
    }

    /**
     * „Diesen und alle folgenden“: Ab dem gewählten Termin gilt die neue
     * Regel. Liegen davor schon Termine, endet die bisherige Serie am Vortag
     * und der Rest wird zu einer eigenen Serie.
     *
     * @param  array<string, mixed>  $attributes  Felder des gewählten Termins inkl. starts_at/ends_at
     * @param  array{frequency: string, interval: int, weekdays?: array<int, int>|null, month_week?: int|null, until?: string|null, count?: int|null}  $rule
     * @param  array<int, int>  $memberIDs
     * @return array{series: ClubEventSeries, created: int, updated: int, deleted: int}
     */
    public function updateFollowing(ClubEvent $occurrence, array $attributes, array $rule, array $memberIDs): array
    {
        return DB::transaction(function () use ($occurrence, $attributes, $rule, $memberIDs) {
            $series = $occurrence->series;
            $fromDay = $occurrence->starts_at->copy()->startOfDay();
            $newStartDay = $attributes['starts_at']->format('Y-m-d');
            $excluded = collect($series->excluded_dates ?? []);

            $following = $series->events()->where('starts_at', '>=', $fromDay)->get();
            $hasEarlier = $series->events()->where('starts_at', '<', $fromDay)->exists();

            $rule['excluded_dates'] = $excluded->filter(fn (string $date) => $date >= $newStartDay)->values()->all();

            if ($hasEarlier) {
                $series->update([
                    'until' => $fromDay->copy()->subDay(),
                    'count' => null,
                    'excluded_dates' => $excluded->filter(fn (string $date) => $date < $fromDay->format('Y-m-d'))->values()->all(),
                ]);

                $series = ClubEventSeries::create($this->seriesAttributes($rule));
            } else {
                $series->update($this->seriesAttributes($rule));
            }

            return ['series' => $series] + $this->syncOccurrences($series, $attributes, $following, $memberIDs);
        });
    }

    /**
     * Einen Termin der Serie löschen; der Tag wird gemerkt, damit er bei
     * späteren Änderungen der Serie nicht wieder entsteht.
     */
    public function deleteOccurrence(ClubEvent $occurrence): void
    {
        DB::transaction(function () use ($occurrence) {
            $series = $occurrence->series;

            $occurrence->delete();

            if (! $series) {
                return;
            }

            $series->update([
                'excluded_dates' => collect($series->excluded_dates ?? [])
                    ->push($occurrence->starts_at->format('Y-m-d'))
                    ->unique()
                    ->sort()
                    ->values()
                    ->all(),
            ]);

            $this->deleteSeriesIfEmpty($series);
        });
    }

    /**
     * Diesen und alle folgenden Termine der Serie löschen.
     *
     * @return int Anzahl gelöschter Termine
     */
    public function deleteFollowing(ClubEvent $occurrence): int
    {
        return DB::transaction(function () use ($occurrence) {
            $series = $occurrence->series;
            $fromDay = $occurrence->starts_at->copy()->startOfDay();

            $deleted = $series->events()->where('starts_at', '>=', $fromDay)->delete();

            $series->update([
                'until' => $fromDay->copy()->subDay(),
                'count' => null,
            ]);

            $this->deleteSeriesIfEmpty($series);

            return $deleted;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  Collection<int, ClubEvent>  $existing
     * @param  array<int, int>  $memberIDs
     * @return array{created: int, updated: int, deleted: int}
     */
    private function syncOccurrences(ClubEventSeries $series, array $attributes, Collection $existing, array $memberIDs): array
    {
        /** @var Carbon $start */
        $start = $attributes['starts_at'];
        $duration = $attributes['ends_at'] ? (int) $start->diffInSeconds($attributes['ends_at']) : null;

        $existingByDay = $existing->sortBy('starts_at')
            ->groupBy(fn (ClubEvent $event) => $event->starts_at->format('Y-m-d'));

        $created = 0;
        $updated = 0;

        foreach (array_slice($this->occurrenceDates($start, $series->rule()), 0, self::MAX_OCCURRENCES) as $date) {
            $day = $date->format('Y-m-d');
            $startsAt = Carbon::parse($day.' '.$start->format('H:i:s'));

            $values = [
                ...$attributes,
                'seriesID' => $series->seriesID,
                'starts_at' => $startsAt,
                'ends_at' => $duration === null ? null : $startsAt->copy()->addSeconds($duration),
            ];

            $event = $existingByDay->get($day)?->shift();

            if ($event) {
                $event->update($values);
                $updated++;
            } else {
                $event = ClubEvent::create($values);
                $created++;
            }

            $event->members()->sync($memberIDs);
        }

        $leftover = $existingByDay->flatten(1);

        ClubEvent::query()->whereKey($leftover->pluck('eventID')->all())->delete();

        return [
            'created' => $created,
            'updated' => $updated,
            'deleted' => $leftover->count(),
        ];
    }

    /**
     * @param  array{frequency: string, interval: int, weekdays?: array<int, int>|null, month_week?: int|null, until?: string|null, count?: int|null, excluded_dates?: array<int, string>}  $rule
     * @return array<string, mixed>
     */
    private function seriesAttributes(array $rule): array
    {
        return [
            'frequency' => $rule['frequency'],
            'interval' => max(1, (int) $rule['interval']),
            'weekdays' => $rule['frequency'] === ClubEventSeries::FREQUENCY_WEEKLY ? array_values($rule['weekdays'] ?? []) : null,
            'month_week' => $rule['frequency'] === ClubEventSeries::FREQUENCY_MONTHLY_WEEKDAY ? $rule['month_week'] : null,
            'until' => $rule['until'] ?? null,
            'count' => $rule['count'] ?? null,
            'excluded_dates' => $rule['excluded_dates'] ?? [],
        ];
    }

    private function deleteSeriesIfEmpty(ClubEventSeries $series): void
    {
        if (! $series->events()->exists()) {
            $series->delete();
        }
    }

    /**
     * Alle Tage, die die Regel ab der Woche bzw. dem Monat des Beginns
     * vorsieht (ohne Ende – der Aufrufer bricht ab).
     *
     * @param  array{frequency: string, interval: int, weekdays?: array<int, int>|null, month_week?: int|null}  $rule
     * @return Generator<int, CarbonImmutable>
     */
    private function candidateDates(CarbonImmutable $first, array $rule): Generator
    {
        $interval = max(1, (int) $rule['interval']);
        $weekdays = collect($rule['weekdays'] ?? [$first->dayOfWeekIso])->map(fn ($weekday) => (int) $weekday)->unique()->sort()->values();

        // Obergrenze gegen Endlosschleifen bei Regeln ohne Treffer
        for ($step = 0; $step < 1000; $step++) {
            if ($rule['frequency'] === ClubEventSeries::FREQUENCY_WEEKLY) {
                $week = $first->startOfWeek(CarbonInterface::MONDAY)->addWeeks($step * $interval);

                foreach ($weekdays as $weekday) {
                    yield $week->addDays($weekday - 1);
                }

                continue;
            }

            $month = $first->startOfMonth()->addMonthsNoOverflow($step * $interval);

            $date = $rule['frequency'] === ClubEventSeries::FREQUENCY_MONTHLY_DAY
                ? ($first->day <= $month->daysInMonth ? $month->setDay($first->day) : null)
                : $this->weekdayOfMonth($month, $first->dayOfWeekIso, (int) ($rule['month_week'] ?? 1));

            if ($date) {
                yield $date;
            }
        }
    }

    /**
     * n-ter (1–4) bzw. letzter (-1) Wochentag eines Monats; null, wenn es
     * ihn in diesem Monat nicht gibt.
     */
    private function weekdayOfMonth(CarbonImmutable $month, int $weekday, int $monthWeek): ?CarbonImmutable
    {
        if ($monthWeek === -1) {
            $lastDay = $month->endOfMonth()->startOfDay();

            return $lastDay->subDays(($lastDay->dayOfWeekIso - $weekday + 7) % 7);
        }

        $firstDay = $month->startOfMonth();
        $date = $firstDay->addDays(($weekday - $firstDay->dayOfWeekIso + 7) % 7 + ($monthWeek - 1) * 7);

        return $date->month === $month->month ? $date : null;
    }
}
