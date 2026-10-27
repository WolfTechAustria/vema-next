<?php

namespace App\Exports;

use App\Services\ClubStatistics;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Kennzahlen der Mitgliedsvereine eines Verbands als Excel-Tabelle.
 */
class FederationStatisticsExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * @param  list<array{club: string, collected_at: ?string, data: ?array<string, int>}>  $rows
     */
    public function __construct(private array $rows) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Verein', 'Stand', ...array_values(ClubStatistics::COLUMNS)];
    }

    /**
     * @return list<list<int|string|null>>
     */
    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['club'],
            $row['collected_at'],
            ...array_map(fn (string $key) => $row['data'][$key] ?? null, array_keys(ClubStatistics::COLUMNS)),
        ], $this->rows);
    }
}
