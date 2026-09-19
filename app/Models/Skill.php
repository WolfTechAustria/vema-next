<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Skill extends Model
{
    protected $table = 'tb_skills';

    protected $primaryKey = 'skillID';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            Member::class,
            'tb_skill_members',
            'skillID',
            'memberID',
            'skillID',
            'memberID'
        )->withTimestamps();
    }

    public function externalContacts(): BelongsToMany
    {
        return $this->belongsToMany(
            ExternalContact::class,
            'tb_skill_external_contacts',
            'skillID',
            'externalContactID',
            'skillID',
            'externalContactID'
        )->withTimestamps();
    }
}
