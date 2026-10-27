<?php

namespace App\Services;

use App\Models\ClubStatistic;
use App\Models\Member;
use App\Models\MemberBoardFunctionAssignment;
use App\Models\MemberMembershipPeriod;
use App\Models\Tenant;

/**
 * Kennzahlen eines Vereins für die Verbandszentrale — nur Anzahlen, keine
 * Namen oder Kontaktdaten. Berechnet im Kontext des Vereins und als Stand
 * in der zentralen Datenbank abgelegt.
 */
class ClubStatistics
{
    /**
     * Spalten der Übersicht (Schlüssel => Überschrift), auch für den Export.
     */
    public const COLUMNS = [
        'members' => 'Aktive Mitglieder',
        'male' => 'Männlich',
        'female' => 'Weiblich',
        'age_under_19' => 'bis 18',
        'age_19_30' => '19–30',
        'age_31_50' => '31–50',
        'age_51_70' => '51–70',
        'age_over_70' => 'über 70',
        'age_unknown' => 'Alter unbekannt',
        'ordinary' => 'Ordentliche Mitglieder',
        'supporting' => 'Unterstützende Mitglieder',
        'joined_this_year' => 'Eintritte lfd. Jahr',
        'left_this_year' => 'Austritte lfd. Jahr',
        'joined_last_year' => 'Eintritte Vorjahr',
        'left_last_year' => 'Austritte Vorjahr',
        'board_functions' => 'Vorstandsfunktionen',
    ];

    /**
     * Kennzahlen des aktuellen Vereins.
     *
     * @return array<string, int>
     */
    public function calculate(): array
    {
        $year = (int) now()->year;
        $active = Member::query()->active();

        $ageGroups = array_fill_keys(['age_under_19', 'age_19_30', 'age_31_50', 'age_51_70', 'age_over_70', 'age_unknown'], 0);

        foreach ((clone $active)->pluck('dateOfBirth') as $dateOfBirth) {
            $ageGroups[$this->ageGroup($dateOfBirth?->age)]++;
        }

        return [
            'year' => $year,
            'members' => (clone $active)->count(),
            'male' => (clone $active)->where('gender', 'm')->count(),
            'female' => (clone $active)->where('gender', 'w')->count(),
            ...$ageGroups,
            'ordinary' => (clone $active)->where('competitionMember', true)->count(),
            'supporting' => (clone $active)->where('supportingMember', true)->count(),
            'joined_this_year' => MemberMembershipPeriod::query()->whereYear('date_from', $year)->count(),
            'left_this_year' => MemberMembershipPeriod::query()->whereYear('date_to', $year)->count(),
            'joined_last_year' => MemberMembershipPeriod::query()->whereYear('date_from', $year - 1)->count(),
            'left_last_year' => MemberMembershipPeriod::query()->whereYear('date_to', $year - 1)->count(),
            'board_functions' => MemberBoardFunctionAssignment::query()->open()->count(),
        ];
    }

    /**
     * Kennzahlen des aktuellen Vereins berechnen und als Stand speichern.
     */
    public function store(Tenant $club): ClubStatistic
    {
        return ClubStatistic::query()->updateOrCreate(
            ['tenant_id' => $club->getKey()],
            ['data' => $this->calculate(), 'collected_at' => now()],
        );
    }

    private function ageGroup(?int $age): string
    {
        return match (true) {
            $age === null => 'age_unknown',
            $age <= 18 => 'age_under_19',
            $age <= 30 => 'age_19_30',
            $age <= 50 => 'age_31_50',
            $age <= 70 => 'age_51_70',
            default => 'age_over_70',
        };
    }
}
