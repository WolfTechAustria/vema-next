<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberMembershipPeriod extends Model
{
    protected $table = 'tb_member_membership_periods';

    protected $primaryKey = 'membershipPeriodID';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(
            Member::class,
            'memberID',
            'memberID'
        );
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('date_to');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereNotNull('date_to');
    }
}
