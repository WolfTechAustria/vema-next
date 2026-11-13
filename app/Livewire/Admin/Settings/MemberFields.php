<?php

namespace App\Livewire\Admin\Settings;

use App\Enums\MemberFieldPortal;
use App\Enums\MemberFieldType;
use App\Models\MemberField;
use App\Services\ActivityLogger;
use App\Services\MemberFields as MemberFieldsService;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Eigene Mitgliederfelder des Vereins pflegen (z. B. Instrument,
 * Verbandsnummer, Trachtenträger).
 */
class MemberFields extends Component
{
    public bool $showForm = false;

    public ?int $editingFieldID = null;

    public string $name = '';

    public string $type = 'text';

    /** Auswahlmöglichkeiten, eine pro Zeile */
    public string $options = '';

    public string $portal = 'hidden';

    /** Auch in der Online-Anmeldung abfragen */
    public bool $onApplication = false;

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('tb_member_fields', 'name')->ignore($this->editingFieldID, 'fieldID'),
            ],
            'type' => ['required', Rule::enum(MemberFieldType::class)],
            'options' => [Rule::requiredIf(MemberFieldType::tryFrom($this->type)?->hasOptions() ?? false), 'string', 'max:5000'],
            'portal' => ['required', Rule::enum(MemberFieldPortal::class)],
            'onApplication' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Bitte eine Bezeichnung eingeben.',
            'name.unique' => 'Dieses Feld gibt es bereits.',
            'options.required' => 'Bitte mindestens eine Auswahlmöglichkeit eingeben (eine pro Zeile).',
        ];
    }

    public function newField(): void
    {
        $this->authorizeAdmin();
        $this->resetForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function editField(int $fieldID): void
    {
        $this->authorizeAdmin();

        $field = MemberField::findOrFail($fieldID);

        $this->editingFieldID = $field->fieldID;
        $this->name = $field->name;
        $this->type = $field->type->value;
        $this->options = implode("\n", $field->options ?? []);
        $this->portal = $field->portal->value;
        $this->onApplication = (bool) $field->on_application;
        $this->showForm = true;

        $this->resetValidation();
    }

    public function saveField(): void
    {
        $this->authorizeAdmin();

        $validated = $this->validate();
        $type = MemberFieldType::from($validated['type']);
        $field = $this->editingFieldID ? MemberField::findOrFail($this->editingFieldID) : null;

        // Vorhandene Werte würden zur neuen Art nicht mehr passen.
        if ($field && $field->type !== $type && $field->values()->exists()) {
            $this->addError('type', 'Die Art kann nicht mehr geändert werden, weil bereits Werte erfasst sind.');

            return;
        }

        $options = $type->hasOptions() ? $this->parseOptions($validated['options']) : null;

        if ($field && $type->hasOptions()) {
            $usedValues = $field->values()->distinct()->pluck('value')
                ->flatMap(fn (string $value) => $type === MemberFieldType::MultiSelect ? app(MemberFieldsService::class)->decodeList($value) : [$value])
                ->unique()
                ->all();
            $missing = array_diff($usedValues, $options);

            if ($missing !== []) {
                $this->addError('options', 'Bereits verwendete Auswahlmöglichkeiten können nicht entfernt werden: '.implode(', ', $missing).'.');

                return;
            }
        }

        $attributes = [
            'name' => trim($validated['name']),
            'type' => $type,
            'options' => $options,
            'portal' => MemberFieldPortal::from($validated['portal']),
            'on_application' => $validated['onApplication'],
        ];

        if ($field) {
            $before = $field->activitySnapshot();
            $field->update($attributes);
            $this->logChanges($field, $before);

            session()->flash('success', 'Feld wurde gespeichert.');
        } else {
            $field = MemberField::create($attributes + [
                'key' => $this->uniqueKey($attributes['name']),
                'sort_order' => (MemberField::max('sort_order') ?? 0) + 1,
                'active' => true,
            ]);

            ActivityLogger::log(
                'member_field.created',
                'Mitgliederfeld „'.$field->name.'“ wurde angelegt.',
                'MemberField',
                $field->fieldID,
                ActivityLogger::created($field->activitySnapshot(), MemberField::ACTIVITY_LABELS)
            );

            session()->flash('success', 'Feld wurde angelegt.');
        }

        $this->resetForm();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->resetValidation();
    }

    public function toggleActive(int $fieldID): void
    {
        $this->authorizeAdmin();

        $field = MemberField::findOrFail($fieldID);
        $before = $field->activitySnapshot();

        $field->update(['active' => ! $field->active]);

        $this->logChanges($field, $before);
    }

    public function moveUp(int $fieldID): void
    {
        $this->swapSortOrder($fieldID, -1);
    }

    public function moveDown(int $fieldID): void
    {
        $this->swapSortOrder($fieldID, 1);
    }

    public function deleteField(int $fieldID): void
    {
        $this->authorizeAdmin();

        $field = MemberField::findOrFail($fieldID);
        $valueCount = $field->values()->count();

        $field->values()->delete();
        $field->delete();

        ActivityLogger::log(
            'member_field.deleted',
            'Mitgliederfeld „'.$field->name.'“ wurde mit '.$valueCount.' '.($valueCount === 1 ? 'Wert' : 'Werten').' gelöscht.',
            'MemberField',
            $field->fieldID
        );

        if ($this->editingFieldID === $fieldID) {
            $this->resetForm();
        }

        session()->flash('success', 'Feld wurde gelöscht.');
    }

    /**
     * @return list<string>
     */
    private function parseOptions(string $options): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $options))
            ->map(fn (string $option) => trim($option))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Platzhalter-Schlüssel aus der Bezeichnung, z. B. „Größe Tracht“ → groesse_tracht.
     */
    private function uniqueKey(string $name): string
    {
        $base = Str::limit(Str::slug(str_replace(['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'], ['ae', 'oe', 'ue', 'ss', 'ae', 'oe', 'ue'], $name), '_'), 50, '') ?: 'feld';
        $key = $base;

        for ($suffix = 2; MemberField::where('key', $key)->exists(); $suffix++) {
            $key = $base.'_'.$suffix;
        }

        return $key;
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function logChanges(MemberField $field, array $before): void
    {
        $changes = ActivityLogger::changes($before, $field->activitySnapshot(), MemberField::ACTIVITY_LABELS);

        if ($changes === []) {
            return;
        }

        ActivityLogger::log(
            'member_field.updated',
            'Mitgliederfeld „'.$field->name.'“ wurde geändert.',
            'MemberField',
            $field->fieldID,
            $changes
        );
    }

    private function swapSortOrder(int $fieldID, int $direction): void
    {
        $this->authorizeAdmin();

        $fields = MemberField::query()->ordered()->get();
        $index = $fields->search(fn (MemberField $field) => $field->fieldID === $fieldID);

        if ($index === false || ! isset($fields[$index + $direction])) {
            return;
        }

        $orderBefore = $fields->pluck('name')->implode(', ');

        $reordered = $fields->values()->all();
        [$reordered[$index], $reordered[$index + $direction]] = [$reordered[$index + $direction], $reordered[$index]];

        foreach ($reordered as $position => $field) {
            if ($field->sort_order !== $position + 1) {
                $field->update(['sort_order' => $position + 1]);
            }
        }

        $moved = $fields[$index];

        ActivityLogger::log(
            'member_field.reordered',
            'Mitgliederfeld „'.$moved->name.'“ wurde nach '.($direction < 0 ? 'oben' : 'unten').' verschoben.',
            'MemberField',
            $moved->fieldID,
            ActivityLogger::changes(
                ['order' => $orderBefore],
                ['order' => collect($reordered)->pluck('name')->implode(', ')],
                ['order' => 'Reihenfolge']
            )
        );
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editingFieldID', 'name', 'type', 'options', 'portal', 'onApplication']);
    }

    public function render()
    {
        return view('livewire.admin.settings.member-fields', [
            'fields' => MemberField::query()->ordered()->withCount('values')->get(),
            'types' => MemberFieldType::cases(),
            'portalOptions' => MemberFieldPortal::cases(),
            'typeLocked' => $this->editingFieldID !== null
                && MemberField::find($this->editingFieldID)?->values()->exists(),
        ])
            ->layout('layouts.app', [
                'title' => 'Mitgliederfelder | VEMA',
                'heading' => 'Einstellungen',
            ]);
    }
}
