<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DutyPlan extends Model
{
    protected $table = 'tb_dutyplans';

    protected $primaryKey = 'planID';

    protected $guarded = [];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'exclude_holidays' => 'boolean',
    ];

    /**
     * Feldnamen für das Aktivitätsprotokoll (siehe activitySnapshot()).
     *
     * @var array<string, string>
     */
    public const ACTIVITY_LABELS = [
        'name' => 'Name',
        'date_from' => 'Von',
        'date_to' => 'Bis',
        'exclude_holidays' => 'Feiertage auslassen',
    ];

    /**
     * @return array<string, mixed>
     */
    public function activitySnapshot(): array
    {
        return [
            'name' => $this->name,
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'exclude_holidays' => (bool) $this->exclude_holidays,
        ];
    }

    /**
     * Bezeichnung im Aktivitätsprotokoll, z. B. „Saison 2026“.
     */
    public function activityName(): string
    {
        return '„'.$this->name.'“';
    }

    const CREATED_AT = 'created_at';

    const UPDATED_AT = 'updated_at';

    public function events()
    {
        return $this->hasMany(
            DutyPlanEvent::class,
            'planID',
            'planID'
        );
    }

    public function roles()
    {
        return $this->hasMany(
            DutyPlanRole::class,
            'planID',
            'planID'
        )->orderBy('sort_order');
    }
}
