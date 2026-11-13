<?php

namespace App\Enums;

/**
 * Art einer Dienst-Anfrage: 1:1-Tausch oder Abgabe ohne Gegendienst.
 */
enum DutySwapType: string
{
    case Swap = 'swap';
    case Handover = 'handover';

    public function label(): string
    {
        return match ($this) {
            self::Swap => 'Tausch',
            self::Handover => 'Abgabe',
        };
    }
}
