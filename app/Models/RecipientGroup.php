<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RecipientGroup extends Model
{
    protected $table = 'tb_recipient_groups';

    protected $primaryKey = 'groupID';

    protected $guarded = [];

    public function groupMembers(): HasMany
    {
        return $this->hasMany(
            RecipientGroupMember::class,
            'groupID',
            'groupID'
        );
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            Member::class,
            'tb_recipient_group_members',
            'groupID',
            'memberID',
            'groupID',
            'memberID'
        )->withTimestamps();
    }

    public function externalContacts(): BelongsToMany
    {
        return $this->belongsToMany(
            ExternalContact::class,
            'tb_recipient_group_external_contacts',
            'groupID',
            'externalContactID',
            'groupID',
            'externalContactID'
        )->withTimestamps();
    }
}
