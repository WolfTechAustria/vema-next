<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CircularRecipient extends Model
{
    protected $table = 'tb_circular_recipients';

    protected $primaryKey = 'recipientID';

    protected $guarded = [];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function circular(): BelongsTo
    {
        return $this->belongsTo(
            Circular::class,
            'circularID',
            'circularID'
        );
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(
            Member::class,
            'memberID',
            'memberID'
        );
    }

    public function externalContact(): BelongsTo
    {
        return $this->belongsTo(
            ExternalContact::class,
            'externalContactID',
            'externalContactID'
        );
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->member) {
            return $this->member->full_name;
        }

        if ($this->externalContact) {
            return $this->externalContact->full_name;
        }

        return $this->email ?? 'Unbekannter Empfänger';
    }
}
