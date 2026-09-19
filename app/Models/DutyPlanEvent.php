<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DutyPlanEvent extends Model
{
    protected $table = 'tb_dutyplan_events';

    protected $primaryKey = 'eventID';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'duty_date' => 'date',
        'required_helpers' => 'integer',
    ];

    public function plan()
    {
        return $this->belongsTo(
            DutyPlan::class,
            'planID',
            'planID'
        );
    }

    public function assignments()
    {
        return $this->hasMany(
            DutyPlanAssignment::class,
            'eventID',
            'eventID'
        );
    }

    public function role()
    {
        return $this->belongsTo(
            DutyPlanRole::class,
            'roleID',
            'roleID'
        );
    }
}
