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

    /**
     * Feldnamen für das Aktivitätsprotokoll (siehe activitySnapshot()).
     *
     * @var array<string, string>
     */
    public const ACTIVITY_LABELS = [
        'name' => 'Bezeichnung',
        'required_helpers' => 'Benötigte Helfer',
        'start_time' => 'Beginn',
        'end_time' => 'Ende',
        'required_group' => 'Pflichtgruppe',
        'required_skill' => 'Pflicht-Fähigkeit',
        'weekdays' => 'Wochentage',
    ];

    /**
     * @return array<string, mixed>
     */
    public function activitySnapshot(): array
    {
        $this->unsetRelation('requiredGroup')->unsetRelation('requiredSkill');

        return [
            'name' => $this->name,
            'required_helpers' => $this->required_helpers,
            'start_time' => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr($this->end_time, 0, 5) : null,
            'required_group' => $this->requiredGroup
                ? $this->requiredGroup->name.' (mind. '.$this->required_group_min.')'
                : null,
            'required_skill' => $this->requiredSkill?->name,
            'weekdays' => self::formatWeekdayMask((int) $this->weekday_mask),
        ];
    }

    /**
     * Wochentage einer Bitmaske in Kalenderreihenfolge, z. B. „Mo, Mi, Fr“.
     */
    public static function formatWeekdayMask(int $mask): ?string
    {
        $names = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

        $selected = array_values(array_filter(
            $names,
            fn (string $name, int $index) => ($mask & (1 << $index)) !== 0,
            ARRAY_FILTER_USE_BOTH
        ));

        return $selected === [] ? null : implode(', ', $selected);
    }

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
