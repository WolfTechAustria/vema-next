<?php

namespace App\Enums;

/**
 * Arten von Push-Benachrichtigungen, die jede Person einzeln ein- und
 * ausschalten kann.
 */
enum PushCategory: string
{
    case Duties = 'duties';
    case Events = 'events';
    case Documents = 'documents';
    case Administration = 'administration';

    public function label(): string
    {
        return match ($this) {
            self::Duties => 'Dienste',
            self::Events => 'Termine',
            self::Documents => 'Rundschreiben & Dokumente',
            self::Administration => 'Verwaltung',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Duties => 'Erinnerung vor Diensten, neue Einteilungen und Tauschanfragen.',
            self::Events => 'Neue Vereinstermine und Änderungen an Terminen, für die du zugesagt hast.',
            self::Documents => 'Neue Rundschreiben und neue Dokumente für dich.',
            self::Administration => 'Neue Online-Anmeldungen und Diensttausche, die auf Freigabe warten.',
        };
    }

    /**
     * Kategorien für Mitglieder (Mitgliederbereich) bzw. Funktionäre.
     *
     * @return list<self>
     */
    public static function forMembers(): array
    {
        return [self::Duties, self::Events, self::Documents];
    }

    /**
     * @return list<self>
     */
    public static function forStaff(): array
    {
        return [self::Administration];
    }
}
