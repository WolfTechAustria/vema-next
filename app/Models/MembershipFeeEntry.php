<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipFeeEntry extends Model
{
    protected $table = 'tb_membership_fee_entries';

    protected $primaryKey = 'entryID';

    protected $guarded = [];

    protected $casts = [
        'yearID' => 'integer',
        'memberID' => 'integer',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function year()
    {
        return $this->belongsTo(
            MembershipFeeYear::class,
            'yearID',
            'yearID'
        );
    }

    public function member()
    {
        return $this->belongsTo(
            Member::class,
            'memberID',
            'memberID'
        );
    }

    public function isReminderDue(): bool
    {
        $year = $this->year;

        if (!$year || !$year->due_date) {
            return false;
        }

        if ($this->status !== 'open') {
            return false;
        }

        $prescription = $this->prescriptions
            ->where('type', 'prescription')
            ->whereNotNull('sent_at')
            ->sortByDesc('sent_at')
            ->first();

        if (!$prescription) {
            return false;
        }

        $reminders = $this->prescriptions
            ->where('type', 'reminder')
            ->whereNotNull('sent_at');

        $reminderCount = $reminders->count();

        if ($reminderCount >= $year->max_reminders) {
            return false;
        }

        if ($reminderCount === 0) {
            $nextReminderDate = $year->due_date
                ->copy()
                ->addDays($year->first_reminder_after_days);
        } else {
            $lastReminder = $reminders
                ->sortByDesc('sent_at')
                ->first();

            $nextReminderDate = $lastReminder->sent_at
                ->copy()
                ->addDays($year->reminder_interval_days);
        }

        return now()->greaterThanOrEqualTo($nextReminderDate);
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->status === 'paid';
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(
            MembershipFeePrescription::class,
            'entryID',
            'entryID'
        );
    }
}
