<?php

namespace App\Services;

use App\Enums\Feature;
use App\Enums\MemberFieldPortal;
use App\Enums\MemberFieldType;
use App\Models\Member;
use App\Models\MemberField;
use App\Models\MemberFieldValue;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;

/**
 * Eigene Mitgliederfelder: Formularwerte, Validierung, Speichern, Anzeige,
 * Aktivitätsprotokoll und Platzhalter. Ohne die Paketfunktion gibt es keine
 * Felder (die gespeicherten Werte bleiben erhalten).
 */
class MemberFields
{
    public function __construct(private PlanEntitlements $entitlements) {}

    public function isEnabled(): bool
    {
        return $this->entitlements->allows(Feature::MemberFields);
    }

    /**
     * Aktive Felder in ihrer Reihenfolge.
     *
     * @return Collection<int, MemberField>
     */
    public function fields(): Collection
    {
        if (! $this->isEnabled()) {
            return new Collection;
        }

        return MemberField::query()->active()->ordered()->get();
    }

    /**
     * Felder, die Mitglieder im Portal sehen (oder bearbeiten) dürfen.
     *
     * @return Collection<int, MemberField>
     */
    public function portalFields(bool $editableOnly = false): Collection
    {
        return $this->fields()->filter(fn (MemberField $field) => $editableOnly
            ? $field->portal === MemberFieldPortal::Editable
            : $field->portal !== MemberFieldPortal::Hidden)->values();
    }

    /**
     * Gespeicherte Werte eines Mitglieds: fieldID => Rohwert.
     *
     * @return array<int, string>
     */
    public function storedValues(Member $member): array
    {
        return MemberFieldValue::query()
            ->where('memberID', $member->memberID)
            ->pluck('value', 'fieldID')
            ->all();
    }

    /**
     * Startwerte für ein Formular (Ja/Nein als bool, sonst Text).
     *
     * @return array<int, string|bool>
     */
    public function formValues(?Member $member = null, ?Collection $fields = null): array
    {
        $stored = $member ? $this->storedValues($member) : [];

        return ($fields ?? $this->fields())
            ->mapWithKeys(fn (MemberField $field) => [$field->fieldID => $field->type === MemberFieldType::Boolean
                ? ($stored[$field->fieldID] ?? '') === '1'
                : (string) ($stored[$field->fieldID] ?? '')])
            ->all();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(string $prefix = 'customFields', ?Collection $fields = null): array
    {
        return ($fields ?? $this->fields())
            ->mapWithKeys(fn (MemberField $field) => [$prefix.'.'.$field->fieldID => match ($field->type) {
                MemberFieldType::Text => ['nullable', 'string', 'max:255'],
                MemberFieldType::Number => ['nullable', 'regex:/^-?\d+([.,]\d+)?$/', 'max:30'],
                MemberFieldType::Date => ['nullable', 'date_format:Y-m-d'],
                MemberFieldType::Boolean => ['boolean'],
                MemberFieldType::Select => ['nullable', Rule::in($field->options ?? [])],
            }])
            ->all();
    }

    /**
     * Feldnamen für Fehlermeldungen.
     *
     * @return array<string, string>
     */
    public function attributes(string $prefix = 'customFields', ?Collection $fields = null): array
    {
        return ($fields ?? $this->fields())
            ->mapWithKeys(fn (MemberField $field) => [$prefix.'.'.$field->fieldID => $field->name])
            ->all();
    }

    /**
     * Werte speichern; leere Werte (und „Nein“) werden gelöscht.
     *
     * @param  array<int|string, mixed>  $values
     */
    public function save(Member $member, array $values, ?Collection $fields = null): void
    {
        foreach ($fields ?? $this->fields() as $field) {
            $value = $this->normalize($field, $values[$field->fieldID] ?? null);

            if ($value === null) {
                MemberFieldValue::query()
                    ->where('fieldID', $field->fieldID)
                    ->where('memberID', $member->memberID)
                    ->delete();

                continue;
            }

            MemberFieldValue::query()->updateOrCreate(
                ['fieldID' => $field->fieldID, 'memberID' => $member->memberID],
                ['value' => $value],
            );
        }
    }

    /**
     * Darstellung eines gespeicherten Werts, z. B. „03.11.2026“, „Ja“, „1,5“.
     */
    public function display(MemberField $field, ?string $value): ?string
    {
        if ($field->type === MemberFieldType::Boolean) {
            return $value === '1' ? 'Ja' : 'Nein';
        }

        if ($value === null || $value === '') {
            return null;
        }

        return match ($field->type) {
            MemberFieldType::Date => Carbon::parse($value)->format('d.m.Y'),
            MemberFieldType::Number => str_replace('.', ',', $value),
            default => $value,
        };
    }

    /**
     * Befüllte Felder eines Mitglieds für die Anzeige: Feldname => Wert.
     *
     * @return array<string, string>
     */
    public function displayValues(Member $member, ?Collection $fields = null): array
    {
        $stored = $this->storedValues($member);

        return ($fields ?? $this->fields())
            ->filter(fn (MemberField $field) => isset($stored[$field->fieldID]))
            ->mapWithKeys(fn (MemberField $field) => [$field->name => $this->display($field, $stored[$field->fieldID])])
            ->all();
    }

    /**
     * Werte für das Aktivitätsprotokoll: field_<id> => Anzeige.
     *
     * @return array<string, ?string>
     */
    public function snapshot(Member $member): array
    {
        $stored = $this->storedValues($member);

        return $this->fields()
            ->mapWithKeys(fn (MemberField $field) => ['field_'.$field->fieldID => $this->display($field, $stored[$field->fieldID] ?? null)])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function activityLabels(): array
    {
        return $this->fields()
            ->mapWithKeys(fn (MemberField $field) => ['field_'.$field->fieldID => $field->name])
            ->all();
    }

    /**
     * Platzhalter {{feld_<key>}} für Vorlagen (ohne Wert leer).
     *
     * @return array<string, string>
     */
    public function placeholders(?Member $member): array
    {
        $fields = $this->fields();

        if ($fields->isEmpty()) {
            return [];
        }

        $stored = $member ? $this->storedValues($member) : [];

        return $fields
            ->mapWithKeys(fn (MemberField $field) => [$field->placeholder() => isset($stored[$field->fieldID])
                ? (string) $this->display($field, $stored[$field->fieldID])
                : ''])
            ->all();
    }

    private function normalize(MemberField $field, mixed $value): ?string
    {
        if ($field->type === MemberFieldType::Boolean) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return $field->type === MemberFieldType::Number ? str_replace(',', '.', $value) : $value;
    }
}
