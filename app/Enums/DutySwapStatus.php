<?php

namespace App\Enums;

/**
 * Bearbeitungsstand einer Tausch- bzw. Abgabeanfrage für einen Dienst.
 */
enum DutySwapStatus: string
{
    case Pending = 'pending';
    case AwaitingApproval = 'awaiting_approval';
    case Completed = 'completed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Obsolete = 'obsolete';

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Pending, self::AwaitingApproval];
    }

    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Offen',
            self::AwaitingApproval => 'Wartet auf Freigabe',
            self::Completed => 'Getauscht',
            self::Declined => 'Abgelehnt',
            self::Cancelled => 'Zurückgezogen',
            self::Rejected => 'Vom Verein abgelehnt',
            self::Expired => 'Abgelaufen',
            self::Obsolete => 'Hinfällig',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending, self::AwaitingApproval => 'bg-amber-100 text-amber-800',
            self::Completed => 'bg-emerald-100 text-emerald-800',
            self::Declined, self::Rejected => 'bg-red-100 text-red-700',
            self::Cancelled, self::Expired, self::Obsolete => 'bg-slate-100 text-slate-600',
        };
    }
}
