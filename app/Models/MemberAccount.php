<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberAccount extends Authenticatable
{
    protected $table = 'tb_member_accounts';

    protected $primaryKey = 'accountID';

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(
            Member::class,
            'memberID',
            'memberID'
        );
    }
}
