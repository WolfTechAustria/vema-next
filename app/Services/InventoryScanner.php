<?php

namespace App\Services;

use App\Models\InventoryItem;

/**
 * Findet Material zu einem gescannten Barcode bzw. NFC-Tag — über das Feld
 * „Barcode/NFC“ oder die Inventarnummer. Groß-/Kleinschreibung sowie
 * Trennzeichen (NFC-Leser liefern die Seriennummer mal als „04:A2:…“, mal als
 * „04A2…“) spielen keine Rolle.
 */
class InventoryScanner
{
    public function find(string $raw): ?InventoryItem
    {
        $needle = $this->normalize($raw);

        if ($needle === '') {
            return null;
        }

        $normalized = fn (string $column): string => "REPLACE(REPLACE(REPLACE(LOWER({$column}), ':', ''), '-', ''), ' ', '')";

        return InventoryItem::query()
            ->whereRaw($normalized('code').' = ?', [$needle])
            ->first()
            ?? InventoryItem::query()
                ->whereRaw($normalized('inventory_number').' = ?', [$needle])
                ->first();
    }

    /**
     * Gescannter Text ohne Steuerzeichen, Trennzeichen und Groß-/Kleinschreibung.
     */
    public function normalize(string $raw): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $raw) ?? '';

        return mb_strtolower(str_replace([':', '-', ' '], '', trim($clean)));
    }
}
