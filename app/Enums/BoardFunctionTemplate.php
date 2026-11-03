<?php

namespace App\Enums;

/**
 * Vorlagen für den Katalog der Vorstandsfunktionen je Vereinsart. Neue
 * Vereine starten mit „Allgemein“; übernehmen lässt sich eine Vorlage,
 * solange noch keine Funktion vergeben ist (BoardFunctionTemplates).
 */
enum BoardFunctionTemplate: string
{
    case General = 'general';
    case ShootingClub = 'shooting_club';
    case Band = 'band';
    case SportsClub = 'sports_club';

    public function label(): string
    {
        return match ($this) {
            self::General => 'Allgemein',
            self::ShootingClub => 'Schützengilde',
            self::Band => 'Musikkapelle',
            self::SportsClub => 'Sportverein',
        };
    }

    /**
     * Funktionen in ihrer Reihenfolge.
     *
     * @return list<array{name: string, is_top_vorstand: bool, implies_schuetzenrat: bool, is_council: bool}>
     */
    public function functions(): array
    {
        $top = fn (string $name) => self::function($name, top: true);
        $withCouncil = fn (string $name) => self::function($name, impliesCouncil: true);
        $council = fn (string $name) => self::function($name, council: true);
        $plain = fn (string $name) => self::function($name);

        return match ($this) {
            self::General => [
                $top('Obmann/Obfrau'),
                $top('Obmann-Stv.'),
                $withCouncil('Schriftführer'),
                $withCouncil('Schriftführer-Stv.'),
                $withCouncil('Kassier'),
                $withCouncil('Kassier-Stv.'),
                $council('Ausschussmitglied'),
            ],
            self::ShootingClub => [
                $top('Oberschützenmeister'),
                $top('1. Schützenmeister'),
                $top('2. Schützenmeister'),
                $withCouncil('Schriftführer'),
                $withCouncil('Schriftführer Stv.'),
                $withCouncil('Kassier'),
                $withCouncil('Kassier Stv.'),
                $council('Schützenrat'),
            ],
            self::Band => [
                $top('Obmann/Obfrau'),
                $top('Obmann-Stv.'),
                $plain('Kapellmeister/in'),
                $plain('Kapellmeister-Stv.'),
                $plain('Stabführer/in'),
                $withCouncil('Schriftführer'),
                $withCouncil('Kassier'),
                $plain('Jugendreferent/in'),
                $council('Ausschussmitglied'),
            ],
            self::SportsClub => [
                $top('Obmann/Obfrau'),
                $top('Obmann-Stv.'),
                $withCouncil('Schriftführer'),
                $withCouncil('Kassier'),
                $plain('Sportliche Leitung'),
                $plain('Jugendleitung'),
                $council('Beirat'),
            ],
        };
    }

    /**
     * @return array{name: string, is_top_vorstand: bool, implies_schuetzenrat: bool, is_council: bool}
     */
    private static function function(string $name, bool $top = false, bool $impliesCouncil = false, bool $council = false): array
    {
        return [
            'name' => $name,
            'is_top_vorstand' => $top,
            'implies_schuetzenrat' => $impliesCouncil,
            'is_council' => $council,
        ];
    }
}
