<?php

namespace App\Exports;

use App\Models\Member;
use App\Services\MemberColumns;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Mitgliederliste als Excel-Tabelle mit allen Spalten (auch den eigenen
 * Mitgliederfeldern), gleich formatiert wie die Übersicht.
 */
class MembersExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * @param  Collection<int, Member>  $members  mit den Relationen aus MemberColumns::relationsFor()
     */
    public function __construct(private Collection $members) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Nr.', 'Nachname', 'Vorname', ...array_values(app(MemberColumns::class)->catalog())];
    }

    /**
     * @return list<list<int|string|null>>
     */
    public function array(): array
    {
        $columns = app(MemberColumns::class);
        $keys = array_keys($columns->catalog());

        return $this->members
            ->map(fn (Member $member) => [
                $member->memberID,
                $member->surname,
                $member->name,
                ...array_map(fn (string $key) => $columns->value($member, $key), $keys),
            ])
            ->values()
            ->all();
    }
}
