<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipFeeYear extends Model
{
    protected $table = 'tb_membership_fee_years';

    protected $primaryKey = 'yearID';

    protected $guarded = [];

    protected $casts = [
        'year' => 'integer',
        'default_amount' => 'decimal:2',
        'due_date' => 'date',
        'active' => 'boolean',
        'first_reminder_after_days' => 'integer',
        'reminder_interval_days' => 'integer',
        'max_reminders' => 'integer',
    ];

    public function entries()
    {
        return $this->hasMany(
            MembershipFeeEntry::class,
            'yearID',
            'yearID'
        );
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: 'Mitgliedsbeitrag ' . $this->year;
    }
}
