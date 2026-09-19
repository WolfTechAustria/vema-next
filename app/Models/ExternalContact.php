<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ExternalContact extends Model
{
    protected $table = 'tb_external_contacts';

    protected $primaryKey = 'externalContactID';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function getFullNameAttribute(): string
    {
        return trim(
            $this->surname . ' ' . $this->name
        );
    }

    public function recipientGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            RecipientGroup::class,
            'tb_recipient_group_external_contacts',
            'externalContactID',
            'groupID',
            'externalContactID',
            'groupID'
        )->withTimestamps();
    }

    /**
     * Alle Helfer-Zuordnungen dieses Kontakts über alle Dienstpläne
     * (je Dienstplan eine eigene Zeile, siehe tb_dutyplan_plan_volunteers).
     */
    public function dutyPlanVolunteers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(
            DutyPlanVolunteer::class,
            'externalContactID',
            'externalContactID'
        );
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(
            Skill::class,
            'tb_skill_external_contacts',
            'externalContactID',
            'skillID',
            'externalContactID',
            'skillID'
        )->withTimestamps();
    }
}
