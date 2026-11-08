<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ehrung eines Mitglieds.
 */
class MemberHonor extends Model
{
    protected $table = 'tb_member_honors';

    protected $primaryKey = 'memberHonorID';

    protected $guarded = [];

    protected $casts = [
        'awarded_on' => 'date',
    ];

    /**
     * @return BelongsTo<Honor, $this>
     */
    public function honor(): BelongsTo
    {
        return $this->belongsTo(Honor::class, 'honorID', 'honorID');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'memberID', 'memberID');
    }

    /**
     * Kurzform für Listen, z. B. „Ehrennadel in Gold (2024)“.
     */
    public function label(): string
    {
        return $this->honor->name.($this->awarded_on ? ' ('.$this->awarded_on->year.')' : '');
    }

    /**
     * Ausführlich für das Aktivitätsprotokoll, z. B. „Ehrennadel in Gold, 03.05.2024, Landesverband“.
     */
    public function description(): string
    {
        return implode(', ', array_filter([
            $this->honor->name,
            $this->awarded_on?->format('d.m.Y'),
            $this->note,
        ]));
    }
}
