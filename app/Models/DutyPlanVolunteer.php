<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DutyPlanVolunteer extends Model
{
    protected $table = 'tb_dutyplan_volunteers';

    protected $primaryKey = 'memberID';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'weekday_mask' => 'integer',
    ];

    public function member()
    {
        return $this->belongsTo(
            Member::class,
            'memberID',
            'memberID'
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
