<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DutyPlanVolunteer extends Model
{
    protected $table = 'tb_dutyplan_plan_volunteers';

    protected $primaryKey = 'planVolunteerID';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'weekday_mask' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            DutyPlan::class,
            'planID',
            'planID'
        );
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(
            Member::class,
            'memberID',
            'memberID'
        );
    }

    public function externalContact(): BelongsTo
    {
        return $this->belongsTo(
            ExternalContact::class,
            'externalContactID',
            'externalContactID'
        );
    }

    public function getIsExternalAttribute(): bool
    {
        return $this->externalContactID !== null;
    }

    public function getVolunteerKeyAttribute(): string
    {
        return $this->is_external
            ? 'external:' . $this->externalContactID
            : 'member:' . $this->memberID;
    }

    public static function parseVolunteerKey(?string $key): ?array
    {
        if (!$key) {
            return null;
        }

        [$type, $id] = array_pad(explode(':', $key, 2), 2, null);

        if (!in_array($type, ['member', 'external'], true) || !$id) {
            return null;
        }

        return ['type' => $type, 'id' => (int) $id];
    }

    public function setWeekdayAvailability(
        int $weekday,
        bool $available
    ): void {
        $bit = 1 << ($weekday - 1);

        if ($available) {
            $this->weekday_mask |= $bit;
        } else {
            $this->weekday_mask &= ~$bit;
        }

        $this->save();
    }

    public function isAvailableOnWeekday(int $weekday): bool
    {
        $bit = 1 << ($weekday - 1);

        return ($this->weekday_mask & $bit) !== 0;
    }
}
