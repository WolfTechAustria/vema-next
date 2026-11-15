<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anwesenheit eines Mitglieds bei einem Vereinstermin.
 */
class ClubEventAttendance extends Model
{
    protected $table = 'tb_event_attendance';

    protected $primaryKey = 'attendanceID';

    protected $guarded = [];

    protected $casts = [
        'present' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'eventID', 'eventID');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'memberID', 'memberID');
    }
}
