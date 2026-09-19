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
