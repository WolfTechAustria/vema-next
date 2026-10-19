<?php

namespace App\Services;

use App\Models\MemberMembershipPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Mitgliederentwicklung je Kalenderjahr aus den Mitgliedschaftsperioden:
 * Bestand zum Jahresende (im laufenden Jahr: heute) sowie Ein- und
 * Austritte im Jahr. Wird in PHP gerechnet, damit es auf jeder Datenbank
 * gleich funktioniert — die Zahl der Perioden ist überschaubar.
 */
class MemberHistory
{
    /**
     * @return list<array{year: int, stock: int, joined: int, left: int}>
     */
    public function years(int $count): array
    {
        $periods = MemberMembershipPeriod::query()
            ->whereNotNull('date_from')
            ->get(['memberID', 'date_from', 'date_to']);

        $currentYear = (int) now()->year;

        return collect(range($currentYear - $count + 1, $currentYear))
            ->map(fn (int $year): array => [
                'year' => $year,
                'stock' => $this->stockAt($periods, $this->cutoff($year, $currentYear)),
                'joined' => $periods->filter(fn (MemberMembershipPeriod $period) => $period->date_from->year === $year)->count(),
                'left' => $periods->filter(fn (MemberMembershipPeriod $period) => $period->date_to?->year === $year)->count(),
            ])
            ->all();
    }

    private function cutoff(int $year, int $currentYear): CarbonImmutable
    {
        return $year === $currentYear
            ? CarbonImmutable::today()
            : CarbonImmutable::create($year, 12, 31);
    }

    /**
     * Ein Mitglied mit mehreren Perioden zählt je Stichtag nur einmal.
     *
     * @param  Collection<int, MemberMembershipPeriod>  $periods
     */
    private function stockAt(Collection $periods, CarbonImmutable $cutoff): int
    {
        return $periods
            ->filter(fn (MemberMembershipPeriod $period) => $period->date_from->lte($cutoff)
                && ($period->date_to === null || $period->date_to->gt($cutoff)))
            ->unique('memberID')
            ->count();
    }
}
