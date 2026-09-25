<?php

namespace App\Enums;

enum DemoResetMode: string
{
    case Nightly = 'nightly';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Nightly => 'Jede Nacht automatisch auf Live-Stand zurücksetzen',
            self::Manual => 'Nur manuell zurücksetzen',
        };
    }
}
