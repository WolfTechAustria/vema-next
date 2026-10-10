<?php

namespace App\Enums;

/**
 * Rückmeldung eines Mitglieds zu einem Vereinstermin.
 */
enum EventResponseStatus: string
{
    case Attending = 'attending';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Attending => 'Zugesagt',
            self::Declined => 'Abgesagt',
        };
    }
}
