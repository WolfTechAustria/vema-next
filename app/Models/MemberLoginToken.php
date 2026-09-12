<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberLoginToken extends Model
{
    protected $table = 'tb_member_login_tokens';

    protected $primaryKey = 'tokenID';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(
            Member::class,
            'memberID',
            'memberID'
        );
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
}
