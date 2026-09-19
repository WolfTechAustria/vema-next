<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MemberDutySettings extends Model
{
    protected $table = 'tb_member_duty_settings';

    protected $primaryKey = 'memberID';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'duty_reminder_enabled' => 'boolean',
        'ical_token_created_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'memberID', 'memberID');
    }

    public static function forMember(Member $member): self
    {
        return self::query()->firstOrCreate(
            ['memberID' => $member->memberID],
            ['duty_reminder_enabled' => true]
        );
    }

    /**
     * Liefert den bestehenden iCal-Token oder erzeugt beim ersten
     * Aufruf einen neuen (64 Zeichen, kollisionssicher genug für
     * einen geheimen Abo-Link).
     */
    public function getOrCreateIcalToken(): string
    {
        if ($this->ical_token) {
            return $this->ical_token;
        }

        return $this->regenerateIcalToken();
    }

    public function regenerateIcalToken(): string
    {
        $this->ical_token = Str::random(64);
        $this->ical_token_created_at = now();
        $this->save();

        return $this->ical_token;
    }
}
