<?php

namespace App\Models;

use App\Enums\EventResponseStatus;
use Database\Factories\ClubEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Allgemeiner Vereinstermin (Sitzung, Fest, Ausrückung …) — unabhängig
 * von den Diensten im Dienstplan.
 */
class ClubEvent extends Model
{
    /** @use HasFactory<ClubEventFactory> */
    use HasFactory;

    protected $table = 'tb_events';

    protected $primaryKey = 'eventID';

    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'all_day' => 'boolean',
        'rsvp_enabled' => 'boolean',
    ];

    public function responses(): HasMany
    {
        return $this->hasMany(ClubEventResponse::class, 'eventID', 'eventID');
    }

    /**
     * Termine, die heute oder später stattfinden (bzw. noch laufen).
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where('starts_at', '>=', now()->startOfDay())
                ->orWhere('ends_at', '>=', now());
        });
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where('starts_at', '<', now()->startOfDay())
            ->where(function (Builder $query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '<', now());
            });
    }

    public function responseFor(Member $member): ?ClubEventResponse
    {
        return $this->responses
            ->firstWhere('memberID', $member->memberID);
    }

    public function countResponses(EventResponseStatus $status): int
    {
        return $this->responses
            ->where('status', $status)
            ->count();
    }

    /**
     * Lesbarer Zeitraum, z. B. „Sa, 12.10.2026, 19:00 – 22:00“.
     */
    public function formattedPeriod(): string
    {
        $start = $this->starts_at;
        $end = $this->ends_at;

        if ($this->all_day) {
            if ($end && ! $end->isSameDay($start)) {
                return $start->translatedFormat('D, d.m.Y').' – '.$end->translatedFormat('D, d.m.Y');
            }

            return $start->translatedFormat('D, d.m.Y').' (ganztägig)';
        }

        if (! $end) {
            return $start->translatedFormat('D, d.m.Y, H:i');
        }

        if ($end->isSameDay($start)) {
            return $start->translatedFormat('D, d.m.Y, H:i').' – '.$end->format('H:i');
        }

        return $start->translatedFormat('D, d.m.Y, H:i').' – '.$end->translatedFormat('D, d.m.Y, H:i');
    }
}
