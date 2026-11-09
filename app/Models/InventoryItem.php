<?php

namespace App\Models;

use App\Enums\InventoryCondition;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Ein Stück Material des Vereins (Tracht, Instrument, Schlüssel …).
 */
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    /**
     * Prüfungen, die in so vielen Tagen fällig werden, gelten als „bald fällig“.
     */
    public const INSPECTION_NOTICE_DAYS = 14;

    public const ACTIVITY_LABELS = [
        'name' => 'Bezeichnung',
        'category' => 'Kategorie',
        'inventory_number' => 'Inventarnummer',
        'code' => 'Barcode/NFC',
        'location' => 'Lagerort',
        'condition' => 'Zustand',
        'purchased_on' => 'Anschaffung',
        'purchase_price' => 'Anschaffungspreis',
        'note' => 'Notiz',
        'inspection_interval_months' => 'Prüfintervall (Monate)',
        'next_inspection_on' => 'Nächste Prüfung',
    ];

    protected $table = 'tb_inventory_items';

    protected $primaryKey = 'itemID';

    protected $guarded = [];

    protected $casts = [
        'condition' => InventoryCondition::class,
        'purchased_on' => 'date',
        'purchase_price' => 'decimal:2',
        'inspection_interval_months' => 'integer',
        'next_inspection_on' => 'date',
    ];

    /**
     * @return BelongsTo<InventoryCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'categoryID', 'categoryID');
    }

    /**
     * @return HasMany<InventoryLoan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(InventoryLoan::class, 'itemID', 'itemID')
            ->orderByDesc('issued_on')
            ->orderByDesc('loanID');
    }

    /**
     * Aktuelle (nicht zurückgegebene) Ausgabe.
     *
     * @return HasOne<InventoryLoan, $this>
     */
    public function currentLoan(): HasOne
    {
        return $this->hasOne(InventoryLoan::class, 'itemID', 'itemID')->whereNull('returned_on');
    }

    /**
     * @return HasMany<InventoryInspection, $this>
     */
    public function inspections(): HasMany
    {
        return $this->hasMany(InventoryInspection::class, 'itemID', 'itemID')
            ->orderByDesc('inspected_on')
            ->orderByDesc('inspectionID');
    }

    /**
     * Prüfung überfällig oder in den nächsten Tagen fällig.
     *
     * @param  Builder<InventoryItem>  $query
     */
    public function scopeInspectionDue(Builder $query): void
    {
        $query->whereNotNull('next_inspection_on')
            ->whereDate('next_inspection_on', '<=', today()->addDays(self::INSPECTION_NOTICE_DAYS))
            ->where('condition', '!=', InventoryCondition::Retired->value);
    }

    /**
     * Nicht ausgeschiedenes Material.
     *
     * @param  Builder<InventoryItem>  $query
     */
    public function scopeInService(Builder $query): void
    {
        $query->where('condition', '!=', InventoryCondition::Retired->value);
    }

    public function isInspectionDue(): bool
    {
        return $this->next_inspection_on !== null
            && $this->condition !== InventoryCondition::Retired
            && $this->next_inspection_on->lte(today()->addDays(self::INSPECTION_NOTICE_DAYS));
    }

    public function isInspectionOverdue(): bool
    {
        return $this->next_inspection_on !== null
            && $this->condition !== InventoryCondition::Retired
            && $this->next_inspection_on->lt(today());
    }

    /**
     * Kurzbezeichnung für Listen, z. B. „Tracht Herren (T-012)“.
     */
    public function label(): string
    {
        return $this->name.($this->inventory_number ? ' ('.$this->inventory_number.')' : '');
    }

    /**
     * @return array<string, string|null>
     */
    public function activitySnapshot(): array
    {
        return [
            'name' => $this->name,
            'category' => $this->category?->name,
            'inventory_number' => $this->inventory_number,
            'code' => $this->code,
            'location' => $this->location,
            'condition' => $this->condition?->label(),
            'purchased_on' => $this->purchased_on?->format('d.m.Y'),
            'purchase_price' => $this->purchase_price !== null ? number_format((float) $this->purchase_price, 2, ',', '.').' €' : null,
            'note' => $this->note,
            'inspection_interval_months' => $this->inspection_interval_months !== null ? (string) $this->inspection_interval_months : null,
            'next_inspection_on' => $this->next_inspection_on?->format('d.m.Y'),
        ];
    }
}
