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
     * Startwerte für ein Formular (Ja/Nein als bool, Mehrfachauswahl als
     * Liste, sonst Text).
     *
     * @return array<int, string|bool|list<string>>
     */
    public function formValues(?Member $member = null, ?Collection $fields = null): array
    {
        $stored = $member ? $this->storedValues($member) : [];

        return ($fields ?? $this->fields())
            ->mapWithKeys(fn (MemberField $field) => [$field->fieldID => match ($field->type) {
                MemberFieldType::Boolean => ($stored[$field->fieldID] ?? '') === '1',
                MemberFieldType::MultiSelect => $this->decodeList($stored[$field->fieldID] ?? null),
                default => (string) ($stored[$field->fieldID] ?? ''),
            }])
            ->all();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(string $prefix = 'customFields', ?Collection $fields = null): array
    {
        $rules = [];

        foreach ($fields ?? $this->fields() as $field) {
            $key = $prefix.'.'.$field->fieldID;

            if ($field->type === MemberFieldType::MultiSelect) {
                $rules[$key] = ['nullable', 'array'];
                $rules[$key.'.*'] = [Rule::in($field->options ?? [])];

                continue;
            }

            $rules[$key] = match ($field->type) {
                MemberFieldType::Text => ['nullable', 'string', 'max:255'],
                MemberFieldType::Number => ['nullable', 'regex:/^-?\d+([.,]\d+)?$/', 'max:30'],
                MemberFieldType::Date => ['nullable', 'date_format:Y-m-d'],
                MemberFieldType::Boolean => ['boolean'],
                MemberFieldType::Select => ['nullable', Rule::in($field->options ?? [])],
            };
        }

        return $rules;
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
            MemberFieldType::MultiSelect => implode(', ', $this->decodeList($value)) ?: null,
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

    /**
     * Gespeicherte Mehrfachauswahl als Liste (JSON-Text in der Datenbank).
     *
     * @return list<string>
     */
    public function decodeList(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [$value];
    }

    /**
     * Text, wie eine Mehrfachauswahl-Option gespeichert ist — zum Suchen.
     */
    public function encodedOption(string $option): string
    {
        return json_encode($option, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Formular- oder Rohwert in die gespeicherte Form bringen (null = leer).
     */
    public function normalize(MemberField $field, mixed $value): ?string
    {
        if ($field->type === MemberFieldType::Boolean) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : null;
        }

        if ($field->type === MemberFieldType::MultiSelect) {
            $selected = is_array($value) ? $value : $this->decodeList(is_string($value) ? $value : null);
            $selected = array_map(fn ($option) => trim((string) $option), $selected);

            // In der Reihenfolge der Auswahlmöglichkeiten, ohne Doppelte.
            $ordered = array_values(array_filter($field->options ?? [], fn (string $option) => in_array($option, $selected, true)));

            return $ordered === [] ? null : json_encode($ordered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return $field->type === MemberFieldType::Number ? str_replace(',', '.', $value) : $value;
    }
}
