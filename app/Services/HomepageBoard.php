<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberBoardFunctionAssignment;
use Illuminate\Support\Collection;

/**
 * Vorstand für die öffentliche Homepage: aktuell besetzte, aktive
 * Vorstandsfunktionen aus VEMA — je Person einmal, mit allen Funktionen.
 * Veröffentlicht werden nur Name, Funktionen und (optional) das Profilbild.
 */
class HomepageBoard
{
    public function __construct(private HomepageImageUrls $imageUrls) {}

    /**
     * Je Person ein Eintrag mit allen Funktionen, z. B. „1. Schützenmeister & Kassier“.
     *
     * @return Collection<int, array{name: string, functions: list<string>, label: string, photoUrl: ?string, initials: string}>
     */
    public function entries(bool $withPhotos): Collection
    {
        return $this->openAssignments()
            ->groupBy('memberID')
            ->map(function (Collection $assignments) use ($withPhotos): array {
                /** @var Member $member */
                $member = $assignments->first()->member;
                $functions = $assignments->map(fn ($assignment) => $assignment->boardFunction->name)->unique()->values()->all();

                return [
                    'name' => $member->full_name,
                    'functions' => $functions,
                    'label' => self::joinFunctions($functions),
                    'photoUrl' => $withPhotos ? $this->imageUrls->boardPhoto($member) : null,
                    'initials' => $member->initials(),
                ];
            })
            ->values();
    }

    /**
     * Vorstand als Tabelle: Personen mit mehreren Funktionen in einer Zeile
     * („1. Schützenmeister & Kassier | Sonja Strillinger“), Personen mit
     * gleicher Funktion zusammengefasst („Schützenrat | A, B, C“). Sortiert
     * nach der Reihenfolge der (ersten) Funktion.
     *
     * @return Collection<int, array{function: string, names: list<string>}>
     */
    public function byFunction(): Collection
    {
        return $this->openAssignments()
            ->groupBy('memberID')
            ->map(function (Collection $assignments): array {
                $functions = $assignments->map(fn ($assignment) => $assignment->boardFunction)->unique('boardFunctionID')->values();

                return [
                    'label' => self::joinFunctions($functions->pluck('name')->all()),
                    'sort' => $functions->first()->sort_order,
                    'name' => $assignments->first()->member->full_name,
                    'surname' => (string) $assignments->first()->member->surname,
                ];
            })
            ->groupBy('label')
            ->map(fn (Collection $people, string $label): array => [
                'function' => $label,
                'sort' => $people->min('sort'),
                'names' => $people->sortBy('surname')->pluck('name')->values()->all(),
            ])
            ->sortBy([['sort', 'asc'], ['function', 'asc']])
            ->map(fn (array $row): array => ['function' => $row['function'], 'names' => $row['names']])
            ->values();
    }

    /**
     * „A“, „A & B“, „A, B & C“.
     *
     * @param  list<string>  $functions
     */
    public static function joinFunctions(array $functions): string
    {
        $last = array_pop($functions);

        return $functions === [] ? (string) $last : implode(', ', $functions).' & '.$last;
    }

    /**
     * @return Collection<int, MemberBoardFunctionAssignment>
     */
    private function openAssignments(): Collection
    {
        return MemberBoardFunctionAssignment::query()
            ->open()
            ->whereHas('boardFunction', fn ($query) => $query->active())
            ->whereHas('member', fn ($query) => $query->active())
            ->with(['boardFunction', 'member'])
            ->get()
            ->sortBy([
                fn ($a, $b) => $a->boardFunction->sort_order <=> $b->boardFunction->sort_order,
                fn ($a, $b) => $a->boardFunction->name <=> $b->boardFunction->name,
            ]);
    }

    /**
     * Darf das Profilbild dieses Mitglieds öffentlich gezeigt werden?
     */
    public function isPublicMember(int $memberID): bool
    {
        return MemberBoardFunctionAssignment::query()
            ->open()
            ->where('memberID', $memberID)
            ->whereHas('boardFunction', fn ($query) => $query->active())
            ->whereHas('member', fn ($query) => $query->active())
            ->exists();
    }
}
