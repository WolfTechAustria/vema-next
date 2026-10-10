<?php

namespace App\Models;

use Database\Factories\ClubEventSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Externer iCal-Kalender, dessen Termine regelmäßig in tb_events
 * übernommen werden. Die URL ist verschlüsselt gespeichert, weil private
 * Kalender-Links (z. B. Google) ein Geheimnis enthalten.
 */
class ClubEventSource extends Model
{
    /** @use HasFactory<ClubEventSourceFactory> */
    use HasFactory;

    protected $table = 'tb_event_sources';

    protected $primaryKey = 'sourceID';

    protected $guarded = [];

    protected $hidden = [
        'url',
    ];

    protected $casts = [
        'url' => 'encrypted',
        'rsvp_enabled' => 'boolean',
        'active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(ClubEvent::class, 'sourceID', 'sourceID');
    }

    public function subscribedMembers(): BelongsToMany
    {
        return $this->belongsToMany(
            Member::class,
            'tb_member_event_source_subscriptions',
            'sourceID',
            'memberID',
            'sourceID',
            'memberID'
        )->withTimestamps();
    }
}
