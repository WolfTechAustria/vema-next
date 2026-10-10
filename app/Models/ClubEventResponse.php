<?php

namespace App\Models;

use App\Enums\EventResponseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubEventResponse extends Model
{
    protected $table = 'tb_event_responses';

    protected $primaryKey = 'responseID';

    protected $guarded = [];

    protected $casts = [
        'status' => EventResponseStatus::class,
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
