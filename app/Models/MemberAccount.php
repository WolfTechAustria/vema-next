<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            Member::class,
            'tb_member_account_members',
            'accountID',
            'memberID',
            'accountID',
            'memberID'
        )
            ->withPivot('relation')
            ->withTimestamps();
    }
}
