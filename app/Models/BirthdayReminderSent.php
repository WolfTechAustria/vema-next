<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BirthdayReminderSent extends Model
{
    protected $table = 'tb_birthday_reminders_sent';

    protected $primaryKey = 'reminderID';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'age' => 'integer',
        'birthday_date' => 'date',
        'sent_at' => 'datetime',
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
