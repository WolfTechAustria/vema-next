<?php

namespace App\Enums;

/**
 * Pakete laut Preisliste auf vemat.at: Funktionsumfang und Limits.
 */
enum Plan: string
{
    case Starter = 'starter';
    case Basis = 'basis';
    case Verein = 'verein';
    case VereinPlus = 'verein_plus';
    case Grossverein = 'grossverein';
    case Verband = 'verband';

    public function label(): string
    {
        return match ($this) {
            self::Starter => 'Starter',
            self::Basis => 'Basis',
            self::Verein => 'Verein',
            self::VereinPlus => 'Verein Plus',
            self::Grossverein => 'Großverein',
            self::Verband => 'Verband',
        };
    }

    /**
     * Reihenfolge der Pakete (für „mindestens Paket X“).
     */
    public function rank(): int
    {
        return match ($this) {
            self::Starter => 0,
            self::Basis => 1,
            self::Verein => 2,
            self::VereinPlus => 3,
            self::Grossverein, self::Verband => 4,
        };
    }

    /**
     * Funktionen dieses Pakets — jedes Paket enthält alle der kleineren.
     *
     * @return array<int, Feature>
     */
    public function features(): array
    {
        $features = [];

        if ($this->rank() >= self::Basis->rank()) {
            array_push($features, Feature::CashBookReceipts, Feature::Templates);
        }

        if ($this->rank() >= self::Verein->rank()) {
            array_push($features, Feature::DutyPlan, Feature::Invoices, Feature::RecipientGroups);
        }

        if ($this->rank() >= self::VereinPlus->rank()) {
            array_push($features, Feature::MemberPortal, Feature::ActivityLog, Feature::TestMode);
        }

        return $features;
    }

    public function includes(Feature $feature): bool
    {
        return in_array($feature, $this->features(), true);
    }

    /**
     * Aktive Mitglieder; null = unbegrenzt.
     */
    public function memberLimit(): ?int
    {
        return match ($this) {
            self::Starter => 10,
            self::Basis => 50,
            self::Verein => 150,
            self::VereinPlus => 500,
            self::Grossverein, self::Verband => null,
        };
    }

    /**
     * Aktive Vorstandszugänge (Staff-Benutzer); null = unbegrenzt.
     */
    public function staffLimit(): ?int
    {
        return match ($this) {
            self::Starter => 1,
            self::Basis => 2,
            self::Verein => 3,
            self::VereinPlus => 10,
            self::Grossverein, self::Verband => null,
        };
    }

    /**
     * Kleinstes Paket, das die Funktion enthält.
     */
    public static function lowestWith(Feature $feature): self
    {
        foreach (self::cases() as $plan) {
            if ($plan->includes($feature)) {
                return $plan;
            }
        }

        return self::Grossverein;
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
