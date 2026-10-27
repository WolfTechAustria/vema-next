<?php

namespace App\Enums;

/**
 * Mengenbegrenzungen eines Pakets (null = unbegrenzt).
 */
enum Limit: string
{
    case Members = 'members';
    case Staff = 'staff';
    case EmailsPerMonth = 'emails_per_month';
    case StorageMb = 'storage_mb';

    public function label(): string
    {
        return match ($this) {
            self::Members => 'Aktive Mitglieder',
            self::Staff => 'Vorstandszugänge',
            self::EmailsPerMonth => 'E-Mails pro Monat',
            self::StorageMb => 'Speicher (MB)',
        };
    }

    /**
     * Wert für Anzeigen, z. B. "2.000" oder "5 GB".
     */
    public function format(?int $value): string
    {
        if ($value === null) {
            return 'unbegrenzt';
        }

        if ($this === self::StorageMb) {
            return self::formatMegabytes($value);
        }

        return number_format($value, 0, ',', '.');
    }

    public static function formatMegabytes(float $megabytes): string
    {
        if ($megabytes >= 1024) {
            return rtrim(rtrim(number_format($megabytes / 1024, 1, ',', '.'), '0'), ',').' GB';
        }

        return number_format($megabytes, $megabytes < 10 ? 1 : 0, ',', '.').' MB';
    }
}
