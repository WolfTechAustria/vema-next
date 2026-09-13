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
}
