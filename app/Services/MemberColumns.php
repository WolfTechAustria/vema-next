<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberBoardFunctionAssignment;
use App\Models\MemberField;
use App\Models\MemberHonor;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserMemberColumns;
use Illuminate\Database\Eloquent\Collection;

/**
 * Spalten der Mitgliederübersicht und des Excel-Exports. Welche Spalten ein
 * Benutzer sieht: eigene Auswahl → Vereinsstandard → Standard. Der Name ist
 * immer die erste Spalte und gehört nicht zur Auswahl.
 */
class MemberColumns
{
    /** @var list<string> */
    public const DEFAULT = ['status', 'date_of_join'];

    private const BASE = [
        'status' => 'Status',
        'gender' => 'Anrede',
        'date_of_birth' => 'Geburtsdatum',
        'age' => 'Alter',
        'date_of_join' => 'Eintritt',
        'street' => 'Straße',
        'zip' => 'PLZ',
        'city' => 'Ort',
        'email' => 'E-Mail',
        'phone' => 'Telefon',
        'membership' => 'Mitgliedsart',
        'board_functions' => 'Vorstandsfunktionen',
        'honors' => 'Ehrungen',
    ];

    /** @var Collection<int, MemberField>|null je Instanz nur einmal laden (viele Zellen) */
    private ?Collection $fields = null;

    public function __construct(private MemberFields $memberFields) {}

    /**
     * @return Collection<int, MemberField>
     */
    private function fields(): Collection
    {
        return $this->fields ??= $this->memberFields->fields()->keyBy('fieldID');
    }

    /**
     * Alle verfügbaren Spalten: Schlüssel => Überschrift.
     *
     * @return array<string, string>
     */
    public function catalog(): array
    {
        return self::BASE + $this->fields()
            ->mapWithKeys(fn (MemberField $field) => ['field_'.$field->fieldID => $field->name])
            ->all();
    }

    /**
     * Sichtbare Spalten des Benutzers in seiner Reihenfolge.
     *
     * @return list<string>
     */
    public function columnsFor(User $user): array
    {
        $keys = UserMemberColumns::where('userID', $user->id)->value('columns')
            ?? Setting::current()->member_columns
            ?? self::DEFAULT;

        return $this->known($keys);
    }

    public function hasPersonalColumns(User $user): bool
    {
        return UserMemberColumns::where('userID', $user->id)->exists();
    }

    /**
     * @param  list<string>  $keys
     */
    public function save(User $user, array $keys): void
    {
        UserMemberColumns::updateOrCreate(['userID' => $user->id], ['columns' => $this->known($keys)]);
    }

    public function reset(User $user): void
    {
        UserMemberColumns::where('userID', $user->id)->delete();
    }

    /**
     * @param  list<string>  $keys
     */
    public function saveClubDefault(array $keys): void
    {
        Setting::current()->update(['member_columns' => $this->known($keys)]);
    }

    /**
     * Relationen, die für die Spalten gebraucht werden.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public function relationsFor(array $keys): array
    {
        $relations = [
            'city' => 'city',
            'email' => 'emails',
            'phone' => 'phones',
            'board_functions' => 'currentBoardFunctionAssignments.boardFunction',
            'honors' => 'honors.honor',
        ];

        $needed = array_values(array_intersect_key($relations, array_flip($keys)));

        if (array_filter($keys, fn (string $key) => str_starts_with($key, 'field_')) !== []) {
            $needed[] = 'fieldValues';
        }

        return $needed;
    }

    /**
     * Anzeigewert einer Spalte für ein Mitglied.
     */
    public function value(Member $member, string $key): ?string
    {
        if (str_starts_with($key, 'field_')) {
            $field = $this->fields()->get((int) substr($key, 6));
            $value = $member->fieldValues->firstWhere('fieldID', $field?->fieldID)?->value;

            return $field ? $this->memberFields->display($field, $value) : null;
        }

        return match ($key) {
            'status' => $member->active ? 'Aktiv' : 'Inaktiv',
            'gender' => match ($member->gender) {
                'm' => 'Herr',
                'w' => 'Frau',
                default => $member->gender,
            },
            'date_of_birth' => $member->dateOfBirth?->format('d.m.Y'),
            'age' => $member->dateOfBirth ? (string) $member->dateOfBirth->age : null,
            'date_of_join' => $member->dateOfJoin?->format('d.m.Y'),
            'street' => $member->street,
            'zip' => $member->zip !== null ? (string) $member->zip : null,
            'city' => $member->city?->city,
            'email' => $member->emails->pluck('email')->join(', ') ?: null,
            'phone' => $member->phones->pluck('phoneNumber')->filter()->join(', ') ?: null,
            'membership' => implode(', ', array_filter([
                $member->competitionMember ? 'Ordentlich' : null,
                $member->supportingMember ? 'Unterstützend' : null,
            ])) ?: null,
            'board_functions' => $member->currentBoardFunctionAssignments
                ->map(fn (MemberBoardFunctionAssignment $assignment) => $assignment->boardFunction?->name)
                ->filter()
                ->join(', ') ?: null,
            'honors' => $member->honors->map(fn (MemberHonor $honor) => $honor->label())->join(', ') ?: null,
            default => null,
        };
    }

    /**
     * Unbekannte und doppelte Schlüssel verwerfen.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function known(array $keys): array
    {
        return array_values(array_unique(array_intersect($keys, array_keys($this->catalog()))));
    }
}
