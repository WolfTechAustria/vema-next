<?php

namespace App\Enums;

/**
 * Zuordnung einer Mitgliedsart aus der Online-Anmeldung zu den
 * Kennzeichen des Mitglieds (wie in „Mitglied hinzufügen“).
 */
enum MembershipKind: string
{
    case Ordinary = 'ordinary';
    case Supporting = 'supporting';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Ordinary => 'Ordentliches Mitglied',
            self::Supporting => 'Unterstützendes Mitglied',
            self::None => 'Ohne Kennzeichnung',
        };
    }

    /**
     * Kennzeichen des Mitglieds.
     *
     * @return array{competitionMember: bool, supportingMember: bool}
     */
    public function memberFlags(): array
    {
        return [
            'competitionMember' => $this === self::Ordinary,
            'supportingMember' => $this === self::Supporting,
        ];
    }
}
