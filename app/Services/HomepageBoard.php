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
     * @return Collection<int, array{name: string, functions: list<string>, photoUrl: ?string, initials: string}>
     */
    public function entries(bool $withPhotos): Collection
    {
        return $this->openAssignments()
            ->groupBy('memberID')
            ->map(function (Collection $assignments) use ($withPhotos): array {
                /** @var Member $member */
                $member = $assignments->first()->member;

                return [
                    'name' => $member->full_name,
                    'functions' => $assignments->map(fn ($assignment) => $assignment->boardFunction->name)->unique()->values()->all(),
                    'photoUrl' => $withPhotos ? $this->imageUrls->boardPhoto($member) : null,
                    'initials' => $member->initials(),
                ];
            })
            ->values();
    }

    /**
     * Vorstand als Tabelle: je Funktion die Namen aller Inhaber.
     *
     * @return Collection<int, array{function: string, names: list<string>}>
     */
    public function byFunction(): Collection
    {
        return $this->openAssignments()
            ->groupBy('boardFunctionID')
            ->map(fn (Collection $assignments): array => [
                'function' => $assignments->first()->boardFunction->name,
                'names' => $assignments->map(fn ($assignment) => $assignment->member->full_name)->unique()->values()->all(),
            ])
            ->values();
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
