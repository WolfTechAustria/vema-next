<?php

namespace App\Enums;

enum TenantStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Wartet auf Freischaltung',
            self::Active => 'Aktiv',
            self::Suspended => 'Gesperrt',
            self::Expired => 'Lizenz abgelaufen',
        };
    }
}
