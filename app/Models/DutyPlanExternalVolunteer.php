<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DutyPlanExternalVolunteer extends Model
{
    protected $table = 'tb_dutyplan_external_volunteers';

    protected $primaryKey = 'externalContactID';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'weekday_mask' => 'integer',
    ];

    public function externalContact()
    {
        return $this->belongsTo(
            ExternalContact::class,
            'externalContactID',
            'externalContactID'
        );
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
