<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class DutyPlanAssignment extends Model
{
    protected $casts = [
        'slot_no' => 'integer',
    ];

    protected $table = 'tb_dutyplan_assignments';

    protected $primaryKey = 'assignmentID';

    public $timestamps = false;

    protected $guarded = [];

    public function event()
    {
        return $this->belongsTo(
            DutyPlanEvent::class,
            'eventID',
            'eventID'
        );
    }



    public function member()
    {
        return $this->belongsTo(
            Member::class,
            'memberID',
            'memberID'
        );
    }

    public function externalContact()
    {
        return $this->belongsTo(
            ExternalContact::class,
            'externalContactID',
            'externalContactID'
        );
    }
}
