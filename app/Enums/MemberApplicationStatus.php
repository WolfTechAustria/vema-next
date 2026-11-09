<?php

namespace App\Enums;

/**
 * Bearbeitungsstand einer Online-Anmeldung.
 */
enum MemberApplicationStatus: string
{
    case Unverified = 'unverified';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'E-Mail nicht bestätigt',
            self::Pending => 'Offen',
            self::Approved => 'Freigegeben',
            self::Rejected => 'Abgelehnt',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Unverified => 'bg-slate-100 text-slate-600',
            self::Pending => 'bg-amber-100 text-amber-800',
            self::Approved => 'bg-emerald-100 text-emerald-800',
            self::Rejected => 'bg-red-100 text-red-700',
        };
    }
}
