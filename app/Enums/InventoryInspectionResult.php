<?php

namespace App\Enums;

/**
 * Ergebnis einer Prüfung bzw. Wartung.
 */
enum InventoryInspectionResult: string
{
    case Ok = 'ok';
    case Defects = 'defects';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'In Ordnung',
            self::Defects => 'Mängel',
            self::Retired => 'Ausgeschieden',
        };
    }
}
