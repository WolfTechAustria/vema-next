<?php

namespace App\Exports;

use App\Models\InventoryItem;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Materialliste als Excel-Tabelle.
 */
class InventoryExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * @param  Collection<int, InventoryItem>  $items  mit category und currentLoan.member
     */
    public function __construct(private Collection $items) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Inventarnummer', 'Barcode/NFC', 'Bezeichnung', 'Kategorie', 'Lagerort', 'Zustand', 'Ausgegeben an', 'Ausgegeben seit', 'Rückgabe bis', 'Anschaffung', 'Anschaffungspreis', 'Prüfintervall (Monate)', 'Nächste Prüfung', 'Notiz'];
    }

    /**
     * @return list<list<int|float|string|null>>
     */
    public function array(): array
    {
        return $this->items
            ->map(fn (InventoryItem $item) => [
                $item->inventory_number,
                $item->code,
                $item->name,
                $item->category?->name,
                $item->location,
                $item->condition->label(),
                $item->currentLoan?->member?->full_name,
                $item->currentLoan?->issued_on->format('d.m.Y'),
                $item->currentLoan?->due_on?->format('d.m.Y'),
                $item->purchased_on?->format('d.m.Y'),
                $item->purchase_price !== null ? (float) $item->purchase_price : null,
                $item->inspection_interval_months,
                $item->next_inspection_on?->format('d.m.Y'),
                $item->note,
            ])
            ->values()
            ->all();
    }
}
