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
}
