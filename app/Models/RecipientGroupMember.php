<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipientGroupMember extends Model
{
    protected $table = 'tb_recipient_group_members';

    protected $primaryKey = 'id';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(
            RecipientGroup::class,
            'groupID',
            'groupID'
        );
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(
            Member::class,
            'memberID',
            'memberID'
        );
    }
}
