<?php

namespace App\Enums;

/**
 * Art eines eigenen Mitgliederfelds.
 */
enum MemberFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Boolean = 'boolean';
    case Select = 'select';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Number => 'Zahl',
            self::Date => 'Datum',
            self::Boolean => 'Ja/Nein',
            self::Select => 'Auswahlliste',
        };
    }
}
