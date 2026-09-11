<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Circular extends Model
{
    protected $table = 'tb_circulars';

    protected $primaryKey = 'circularID';

    protected $guarded = [];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(
            Template::class,
            'templateID',
            'templateID'
        );
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(
            CircularRecipient::class,
            'circularID',
            'circularID',
        );
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(
            CircularAttachment::class,
            'circularID',
            'circularID'
        );
    }
}
