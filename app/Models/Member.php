<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\MembershipFeePrescription;
use App\Models\CircularRecipient;

class Member extends Model
{
    protected $table = 'tb_members';

    protected $primaryKey = 'memberID';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'competitionMember' => 'boolean',
        'supportingMember' => 'boolean',
        'dateOfBirth' => 'date',
        'dateOfJoin' => 'date',
        'deactiveSince' => 'date',
    ];

    public function getFullNameAttribute(): string
    {
        return trim($this->name . ' ' . $this->surname);
    }

    public function emails()
    {
        return $this->hasMany(
            MemberEmail::class,
            'memberID',
            'memberID'
        );
    }

    public function phones()
    {
        return $this->hasMany(
            MemberPhone::class,
            'memberID',
            'memberID'
        );
    }

    public function city()
    {
        return $this->belongsTo(
            City::class,
            'zip',
            'zip'
        );
    }

    public function dutyVolunteer()
    {
        return $this->hasOne(
            DutyPlanVolunteer::class,
            'memberID',
            'memberID'
        );
    }

    public function dutyAssignments()
    {
        return $this->hasMany(
            DutyPlanAssignment::class,
            'memberID',
            'memberID'
        );
    }

    public function dutyAbsences()
    {
        return $this->hasMany(
            DutyPlanAbsence::class,
            'memberID',
            'memberID'
        );
    }

    public function membershipFeePrescriptions(): HasMany
    {
        return $this->hasMany(
            MembershipFeePrescription::class,
            'memberID',
            'memberID'
        );
    }

    public function circularRecipients(): HasMany
    {
        return $this->hasMany(
            CircularRecipient::class,
            'memberID',
            'memberID'
        );
    }
}
