<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DutyPlanAbsence extends Model
{
    protected $table = 'tb_dutyplan_absences';

    protected $primaryKey = 'absenceID';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
    ];

    public function member()
    {
        return $this->belongsTo(
            Member::class,
            'memberID',
            'memberID'
        );
    }
}
