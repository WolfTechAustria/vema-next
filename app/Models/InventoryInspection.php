<?php

namespace App\Models;

use App\Enums\InventoryInspectionResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prüfung bzw. Wartung eines Materialstücks.
 */
class InventoryInspection extends Model
{
    protected $table = 'tb_inventory_inspections';

    protected $primaryKey = 'inspectionID';

    protected $guarded = [];

    protected $casts = [
        'inspected_on' => 'date',
        'result' => InventoryInspectionResult::class,
    ];

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'itemID', 'itemID');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
