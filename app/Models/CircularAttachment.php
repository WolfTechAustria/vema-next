<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CircularAttachment extends Model
{
    protected $table = 'tb_circular_attachments';

    protected $primaryKey = 'attachmentID';

    protected $guarded = [];

    public function circular(): BelongsTo
    {
        return $this->belongsTo(
            Circular::class,
            'circularID',
            'circularID'
        );
    }
}
