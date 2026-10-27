<?php

namespace App\Enums;

/**
 * Verbindung zwischen Verband und Mitgliedsverein.
 */
enum FederationMembershipStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Declined = 'declined';
    case Left = 'left';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Eingeladen',
            self::Active => 'Mitglied',
            self::Declined => 'Abgelehnt',
            self::Left => 'Ausgetreten',
        };
    }
}
