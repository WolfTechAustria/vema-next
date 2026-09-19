<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DutyPlanRole extends Model
{
    protected $table = 'tb_dutyplan_roles';

    protected $primaryKey = 'roleID';

    protected $guarded = [];

    protected $casts = [
        'weekday_mask' => 'integer',
        'required_helpers' => 'integer',
        'requiredGroupID' => 'integer',
        'required_group_min' => 'integer',
        'requiredSkillID' => 'integer',
        'sort_order' => 'integer',
        'active' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            DutyPlan::class,
            'planID',
            'planID'
        );
    }

    public function events(): HasMany
    {
        return $this->hasMany(
            DutyPlanEvent::class,
            'roleID',
            'roleID'
        );
    }

    public function requiredGroup(): BelongsTo
    {
        return $this->belongsTo(
            RecipientGroup::class,
            'requiredGroupID',
            'groupID'
        );
    }

    public function requiredSkill(): BelongsTo
    {
        return $this->belongsTo(
            Skill::class,
            'requiredSkillID',
            'skillID'
        );
    }

    public function requiresGroup(): bool
    {
        return $this->requiredGroupID !== null;
    }

    public function requiresSkill(): bool
    {
        return $this->requiredSkillID !== null;
    }

    public function setWeekdayActive(int $weekday, bool $active): void
    {
        $bit = 1 << ($weekday - 1);

        if ($active) {
            $this->weekday_mask |= $bit;
        } else {
            $this->weekday_mask &= ~$bit;
        }

        $this->save();
    }

    public function isActiveOnWeekday(int $weekday): bool
    {
        $bit = 1 << ($weekday - 1);

        return ($this->weekday_mask & $bit) !== 0;
    }
}
