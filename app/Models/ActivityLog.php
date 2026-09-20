<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $table = 'tb_activity_log';

    protected $primaryKey = 'activityLogID';

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'userID',
            'id'
        );
    }

    public function memberAccount(): BelongsTo
    {
        return $this->belongsTo(
            MemberAccount::class,
            'memberAccountID',
            'accountID'
        );
    }

    public function getActorNameAttribute(): string
    {
        return $this->user?->username
            ?? $this->memberAccount?->email
            ?? 'System';
    }
}
