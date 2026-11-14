<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Beitrag in einem Lastschrift-Lauf.
 */
class SepaDebitItem extends Model
{
    protected $table = 'tb_sepa_debit_items';

    protected $primaryKey = 'itemID';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'returned_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(SepaDebitRun::class, 'runID', 'runID');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(MembershipFeeEntry::class, 'entryID', 'entryID');
    }

    public function mandate(): BelongsTo
    {
        return $this->belongsTo(SepaMandate::class, 'mandateID', 'mandateID');
    }
}
