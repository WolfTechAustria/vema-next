<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ausgabe eines Materialstücks an ein Mitglied.
 */
class InventoryLoan extends Model
{
    protected $table = 'tb_inventory_loans';

    protected $primaryKey = 'loanID';

    protected $guarded = [];

    protected $casts = [
        'issued_on' => 'date',
        'due_on' => 'date',
        'returned_on' => 'date',
    ];

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'itemID', 'itemID');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'memberID', 'memberID');
    }

    public function isOpen(): bool
    {
        return $this->returned_on === null;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_on !== null && $this->due_on->lt(today());
    }
}
