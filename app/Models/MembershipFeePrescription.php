<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipFeePrescription extends Model
{
    protected $table = 'tb_membership_fee_prescriptions';

    protected $primaryKey = 'prescriptionID';

    protected $guarded = [];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(
            MembershipFeeEntry::class,
            'entryID',
            'entryID'
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

    public function year(): BelongsTo
    {
        return $this->belongsTo(
            MembershipFeeYear::class,
            'yearID',
            'yearID'
        );
    }
}
