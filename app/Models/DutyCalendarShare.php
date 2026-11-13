<?php

namespace App\Models;

use Database\Factories\DutyCalendarShareFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Ein Mitglied (owner) gibt seine Dienste einem anderen Mitglied (viewer)
 * frei. Der Viewer zeigt sie im eigenen Kalender-Abo an oder abonniert sie
 * über einen eigenen Link.
 */
class DutyCalendarShare extends Model
{
    /** @use HasFactory<DutyCalendarShareFactory> */
    use HasFactory;

    protected $table = 'tb_duty_calendar_shares';

    protected $primaryKey = 'shareID';

    protected $guarded = [];

    protected $casts = [
        'in_own_calendar' => 'boolean',
        'ical_token_created_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'ownerID', 'memberID');
    }

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'viewerID', 'memberID');
    }

    /**
     * Nur Freigaben zwischen aktiven Mitgliedern wirken.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereHas('owner', fn (Builder $query) => $query->where('active', true))
            ->whereHas('viewer', fn (Builder $query) => $query->where('active', true));
    }

    public function getOrCreateIcalToken(): string
    {
        return $this->ical_token ?: $this->regenerateIcalToken();
    }

    public function regenerateIcalToken(): string
    {
        $this->ical_token = Str::random(64);
        $this->ical_token_created_at = now();
        $this->save();

        return $this->ical_token;
    }
}
