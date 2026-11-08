<?php

namespace App\Enums;

/**
 * Was Mitglieder im Mitgliederportal mit einem eigenen Feld tun dürfen.
 */
enum MemberFieldPortal: string
{
    case Hidden = 'hidden';
    case Visible = 'visible';
    case Editable = 'editable';

    public function label(): string
    {
        return match ($this) {
            self::Hidden => 'Nicht im Portal',
            self::Visible => 'Im Portal sichtbar',
            self::Editable => 'Im Portal bearbeitbar',
        };
    }
}
