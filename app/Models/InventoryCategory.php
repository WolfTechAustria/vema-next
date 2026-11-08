<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategorie in der Materialverwaltung (z. B. „Tracht“, „Instrument“) —
 * Vorschlagsliste beim Erfassen, entsteht automatisch.
 */
class InventoryCategory extends Model
{
    protected $table = 'tb_inventory_categories';

    protected $primaryKey = 'categoryID';

    protected $guarded = [];

    /**
     * @return HasMany<InventoryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'categoryID', 'categoryID');
    }
}
