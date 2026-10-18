<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Wiederholungsregel eines Serientermins. Die einzelnen Termine liegen als
 * eigene Einträge in tb_events (siehe ClubEventSeriesService).
 */
class ClubEventSeries extends Model
{
    public const FREQUENCY_WEEKLY = 'weekly';

    public const FREQUENCY_MONTHLY_DAY = 'monthly_day';

    public const FREQUENCY_MONTHLY_WEEKDAY = 'monthly_weekday';

    /**
     * @var array<int, string>
     */
    public const WEEKDAY_SHORT = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];

    /**
     * @var array<int, string>
     */
    public const WEEKDAY_NAMES = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];

    protected $table = 'tb_event_series';

    protected $primaryKey = 'seriesID';

    protected $guarded = [];

    protected $casts = [
        'interval' => 'integer',
        'weekdays' => 'array',
        'month_week' => 'integer',
        'until' => 'date',
        'count' => 'integer',
        'excluded_dates' => 'array',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(ClubEvent::class, 'seriesID', 'seriesID');
    }

    /**
     * @return array{frequency: string, interval: int, weekdays: array<int, int>|null, month_week: int|null, until: string|null, count: int|null, excluded_dates: array<int, string>}
     */
    public function rule(): array
    {
        return [
            'frequency' => $this->frequency,
            'interval' => $this->interval,
            'weekdays' => $this->weekdays,
            'month_week' => $this->month_week,
            'until' => $this->until?->format('Y-m-d'),
            'count' => $this->count,
            'excluded_dates' => $this->excluded_dates ?? [],
        ];
    }

    public function describe(): string
    {
        return self::describeRule($this->rule(), $this->events_min_starts_at ?? $this->events()->min('starts_at'));
    }

    /**
     * Lesbare Regel, z. B. „alle 2 Wochen am Di, Do bis 19.01.2027“ oder
     * „monatlich am letzten Freitag, 10-mal“.
     *
     * @param  array{frequency: string, interval: int, weekdays?: array<int, int>|null, month_week?: int|null, until?: string|null, count?: int|null}  $rule
     */
    public static function describeRule(array $rule, CarbonInterface|string|null $start = null): string
    {
        $interval = max(1, (int) $rule['interval']);
        $start = is_string($start) ? Carbon::parse($start) : $start;

        $text = match ($rule['frequency']) {
            self::FREQUENCY_WEEKLY => ($interval === 1 ? 'wöchentlich' : 'alle '.$interval.' Wochen')
                .' am '.collect($rule['weekdays'] ?? [])->sort()->map(fn (int $weekday) => self::WEEKDAY_SHORT[$weekday])->join(', '),
            self::FREQUENCY_MONTHLY_DAY => ($interval === 1 ? 'monatlich' : 'alle '.$interval.' Monate')
                .($start ? ' am '.$start->day.'.' : ''),
            self::FREQUENCY_MONTHLY_WEEKDAY => ($interval === 1 ? 'monatlich' : 'alle '.$interval.' Monate')
                .' am '.((int) ($rule['month_week'] ?? 1) === -1 ? 'letzten' : $rule['month_week'].'.')
                .($start ? ' '.self::WEEKDAY_NAMES[$start->dayOfWeekIso] : ''),
            default => 'wiederholt',
        };

        if (! empty($rule['until'])) {
            return $text.' bis '.Carbon::parse($rule['until'])->format('d.m.Y');
        }

        if (! empty($rule['count'])) {
            return $text.', '.$rule['count'].'-mal';
        }

        return $text;
    }
}
