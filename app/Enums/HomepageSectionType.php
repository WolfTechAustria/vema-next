<?php

namespace App\Enums;

/**
 * Bausteine, aus denen eigene Seiten und „Über uns“ zusammengesetzt werden.
 */
enum HomepageSectionType: string
{
    case Text = 'text';
    case TextImage = 'text_image';
    case Features = 'features';
    case TextBox = 'text_box';
    case ImagePair = 'image_pair';
    case CallToAction = 'cta';
    case Board = 'board';
    case Events = 'events';
    case Columns = 'columns';
    case InfoBox = 'info_box';
    case ItemList = 'list';
    case Image = 'image';

    /**
     * Nimmt andere Abschnitte auf (darf selbst nicht in Spalten liegen).
     */
    public function isContainer(): bool
    {
        return $this === self::Columns;
    }

    public function label(): string
    {
        return match ($this) {
            self::Board => 'Vorstand',
            self::Events => 'Termine',
            self::Columns => 'Zwei Spalten',
            self::InfoBox => 'Info-Box',
            self::ItemList => 'Titel mit Aufzählung',
            self::Image => 'Bild',
            self::Text => 'Text',
            self::TextImage => 'Text mit Bild',
            self::Features => 'Zahlen-Kacheln',
            self::TextBox => 'Text mit Info-Box',
            self::ImagePair => 'Bilderpaar',
            self::CallToAction => 'Aufruf mit Button',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Board => 'Tabelle mit Funktion und Namen – automatisch aus den aktuellen Vorstandsfunktionen in VEMA.',
            self::Events => 'Die nächsten öffentlichen Vereinstermine – automatisch aus VEMA.',
            self::Columns => 'Zwei Spalten nebeneinander, in die weitere Abschnitte gelegt werden – z. B. links Text, rechts eine Info-Box.',
            self::InfoBox => 'Hervorgehobene Box mit Titel, großer Angabe, Text und Button – mit oder ohne Rahmen.',
            self::ItemList => 'Überschrift mit nummerierten Schritten, Punkten oder Häkchen – z. B. „So einfach geht’s“.',
            self::Image => 'Ein einzelnes Bild mit Bildunterschrift – schmal, normal oder in voller Breite.',
            self::Text => 'Überschrift und formatierter Text.',
            self::TextImage => 'Text neben einem Bild (Bild links oder rechts).',
            self::Features => 'Bis zu vier Kacheln mit großer Zahl, Titel und Text – z. B. Ausstattung.',
            self::TextBox => 'Text mit hervorgehobener Box daneben – z. B. Mitgliedsbeitrag mit PDF-Button. Nummerierte Listen werden als Schritte dargestellt.',
            self::ImagePair => 'Zwei Bilder nebeneinander mit Bildunterschrift.',
            self::CallToAction => 'Kurzer Text mit Button – z. B. „Zum Mitgliederbereich“.',
        };
    }
}
