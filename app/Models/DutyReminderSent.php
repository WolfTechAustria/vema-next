<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DutyReminderSent extends Model
{
    protected $table = 'tb_duty_reminders_sent';

    protected $primaryKey = 'reminderID';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}
