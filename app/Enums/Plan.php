<?php

namespace App\Enums;

/**
 * Pakete laut Preisliste auf vemat.at. Funktionsumfang und Limits folgen in
 * Phase 2b; hier nur Kennung und Bezeichnung.
 */
enum Plan: string
{
    case Starter = 'starter';
    case Verein = 'verein';
    case VereinPlus = 'verein_plus';
    case Grossverein = 'grossverein';
    case Verband = 'verband';

    public function label(): string
    {
        return match ($this) {
            self::Starter => 'Starter',
            self::Verein => 'Verein',
            self::VereinPlus => 'Verein Plus',
            self::Grossverein => 'Großverein',
            self::Verband => 'Verband',
        };
    }

    /**
     * Paket aus dem Link der Website (?plan=…), tolerant gegenüber
     * Schreibweisen wie "Verein Plus" oder "verein-plus".
     */
    public static function fromWebsite(?string $value): ?self
    {
        if (blank($value)) {
            return null;
        }

        $normalized = str_replace(['-', ' ', 'ß'], ['_', '_', 'ss'], mb_strtolower(trim($value)));

        return self::tryFrom($normalized);
    }
}
