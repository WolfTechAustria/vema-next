<?php

namespace App\Enums;

/**
 * Gestaltungsvorlagen der öffentlichen Vereinshomepage.
 */
enum HomepageTemplate: string
{
    case Klassisch = 'klassisch';
    case Modern = 'modern';
    case Sportlich = 'sportlich';
    case Verein = 'verein';

    public function label(): string
    {
        return match ($this) {
            self::Klassisch => 'Klassisch',
            self::Modern => 'Modern',
            self::Sportlich => 'Sportlich',
            self::Verein => 'Verein',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Klassisch => 'Ruhig und seriös: Serifenschrift, zentrierter Kopfbereich, News als Liste.',
            self::Modern => 'Hell und luftig: großes Titelbild, News als Kartenraster.',
            self::Sportlich => 'Kräftig und dynamisch: dunkler Kopfbereich, große News-Kacheln.',
            self::Verein => 'Frisch und übersichtlich: Titelbild mit Farbverlauf, Info-Kacheln, News-Karten und ausführliche Fußzeile mit Kontakt.',
        };
    }
}
