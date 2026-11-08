<?php

namespace App\Enums;

/**
 * Zustand eines Materialstücks.
 */
enum InventoryCondition: string
{
    case New = 'new';
    case Good = 'good';
    case Used = 'used';
    case Defective = 'defective';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Neu',
            self::Good => 'Gut',
            self::Used => 'Gebraucht',
            self::Defective => 'Defekt',
            self::Retired => 'Ausgeschieden',
        };
    }

    /**
     * Tailwind-Klassen für das Zustands-Abzeichen.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::New, self::Good => 'bg-emerald-50 text-emerald-700',
            self::Used => 'bg-slate-100 text-slate-600',
            self::Defective => 'bg-red-50 text-red-700',
            self::Retired => 'bg-slate-200 text-slate-500',
        };
    }
}
