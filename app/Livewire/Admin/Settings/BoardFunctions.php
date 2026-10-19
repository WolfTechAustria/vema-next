<?php

namespace App\Livewire\Admin\Settings;

use App\Models\BoardFunction;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Pflege des Vorstandsfunktions-Katalogs, damit auch Vereine ohne
 * Schützengilden-Bezeichnungen (z. B. Obmann statt Oberschützenmeister)
 * ihre eigenen Funktionen führen können.
 */
class BoardFunctions extends Component
{
    public bool $showForm = false;

    public ?int $editingFunctionID = null;

    public string $name = '';

    public bool $is_top_vorstand = false;

    public bool $implies_schuetzenrat = false;

    public bool $is_council = false;

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
                Rule::unique('tb_board_functions', 'name')->ignore($this->editingFunctionID, 'boardFunctionID'),
            ],
            'is_top_vorstand' => ['boolean'],
            'implies_schuetzenrat' => ['boolean'],
            'is_council' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Bitte eine Bezeichnung eingeben.',
            'name.max' => 'Die Bezeichnung darf höchstens 100 Zeichen lang sein.',
            'name.unique' => 'Diese Funktion gibt es bereits.',
        ];
    }

    public function newFunction(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function editFunction(int $boardFunctionID): void
    {
        $function = BoardFunction::findOrFail($boardFunctionID);

        $this->editingFunctionID = $function->boardFunctionID;
        $this->name = $function->name;
        $this->is_top_vorstand = $function->is_top_vorstand;
        $this->implies_schuetzenrat = $function->implies_schuetzenrat;
        $this->is_council = $function->is_council;
        $this->showForm = true;

        $this->resetValidation();
    }

    public function saveFunction(): void
    {
        $validated = $this->validate();

        if ($validated['is_council'] && $validated['implies_schuetzenrat']) {
            $this->addError('implies_schuetzenrat', 'Die Rats-Funktion kann sich nicht selbst zusätzlich zuweisen.');

            return;
        }

        $attributes = [
            'name' => trim($validated['name']),
            'is_top_vorstand' => $validated['is_top_vorstand'],
            'implies_schuetzenrat' => $validated['implies_schuetzenrat'],
            'is_council' => $validated['is_council'],
        ];

        DB::transaction(function () use ($attributes) {
            if ($attributes['is_council']) {
                $this->removeCouncilFlagFromOthers();
            }

            if ($this->editingFunctionID) {
                $function = BoardFunction::findOrFail($this->editingFunctionID);
                $before = $function->activitySnapshot();

                $function->update($attributes);

                $this->logChanges($function, $before);

                session()->flash('success', 'Funktion wurde gespeichert.');

                return;
            }

            $function = BoardFunction::create($attributes + [
                'sort_order' => (BoardFunction::max('sort_order') ?? 0) + 1,
                'active' => true,
            ]);

            ActivityLogger::log(
                'board_function.created',
                'Vorstandsfunktion „'.$function->name.'“ wurde angelegt.',
                'BoardFunction',
                $function->boardFunctionID,
                ActivityLogger::created($function->activitySnapshot(), BoardFunction::ACTIVITY_LABELS)
            );

            session()->flash('success', 'Funktion wurde angelegt.');
        });

        $this->resetForm();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->resetValidation();
    }

    public function toggleActive(int $boardFunctionID): void
    {
        $function = BoardFunction::findOrFail($boardFunctionID);
        $before = $function->activitySnapshot();

        $function->update(['active' => ! $function->active]);

        $this->logChanges($function, $before);
    }

    public function moveUp(int $boardFunctionID): void
    {
        $this->swapSortOrder($boardFunctionID, -1);
    }

    public function moveDown(int $boardFunctionID): void
    {
        $this->swapSortOrder($boardFunctionID, 1);
    }

    public function deleteFunction(int $boardFunctionID): void
    {
        $function = BoardFunction::findOrFail($boardFunctionID);

        // Zuweisungen hängen per cascadeOnDelete an der Funktion —
        // Löschen würde den Funktionsverlauf (Chronik) mitlöschen.
        if ($function->assignments()->exists()) {
            session()->flash(
                'error',
                'Die Funktion „'.$function->name.'“ wurde bereits Mitgliedern zugewiesen und kann nicht gelöscht werden. Bitte stattdessen deaktivieren.'
            );

            return;
        }

        $function->delete();

        ActivityLogger::log(
            'board_function.deleted',
            'Vorstandsfunktion „'.$function->name.'“ wurde gelöscht.',
            'BoardFunction',
            $function->boardFunctionID
        );

        if ($this->editingFunctionID === $boardFunctionID) {
            $this->resetForm();
        }

        session()->flash('success', 'Funktion wurde gelöscht.');
    }

    private function removeCouncilFlagFromOthers(): void
    {
        BoardFunction::where('is_council', true)
            ->when($this->editingFunctionID, fn ($query) => $query->whereKeyNot($this->editingFunctionID))
            ->get()
            ->each(function (BoardFunction $previous) {
                $before = $previous->activitySnapshot();

                $previous->update(['is_council' => false]);

                $this->logChanges($previous, $before);
            });
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function logChanges(BoardFunction $function, array $before): void
    {
        $changes = ActivityLogger::changes($before, $function->activitySnapshot(), BoardFunction::ACTIVITY_LABELS);

        if ($changes === []) {
            return;
        }

        ActivityLogger::log(
            'board_function.updated',
            'Vorstandsfunktion „'.$function->name.'“ wurde geändert.',
            'BoardFunction',
            $function->boardFunctionID,
            $changes
        );
    }

    private function swapSortOrder(int $boardFunctionID, int $direction): void
    {
        $functions = BoardFunction::orderBy('sort_order')->orderBy('boardFunctionID')->get();

        $index = $functions->search(fn (BoardFunction $function) => $function->boardFunctionID === $boardFunctionID);

        if ($index === false) {
            return;
        }

        $swapIndex = $index + $direction;

        if ($swapIndex < 0 || $swapIndex >= $functions->count()) {
            return;
        }

        $orderBefore = $functions->pluck('name')->implode(', ');

        // Neu durchnummerieren, damit auch gleiche Sortierwerte sauber tauschen.
        $reordered = $functions->values()->all();
        [$reordered[$index], $reordered[$swapIndex]] = [$reordered[$swapIndex], $reordered[$index]];

        foreach ($reordered as $position => $function) {
            if ($function->sort_order !== $position + 1) {
                $function->update(['sort_order' => $position + 1]);
            }
        }

        $moved = $functions[$index];

        ActivityLogger::log(
            'board_function.reordered',
            'Vorstandsfunktion „'.$moved->name.'“ wurde nach '.($direction < 0 ? 'oben' : 'unten').' verschoben.',
            'BoardFunction',
            $moved->boardFunctionID,
            ActivityLogger::changes(
                ['order' => $orderBefore],
                ['order' => collect($reordered)->pluck('name')->implode(', ')],
                ['order' => 'Reihenfolge']
            )
        );
    }

    private function resetForm(): void
    {
        $this->reset([
            'showForm',
            'editingFunctionID',
            'name',
            'is_top_vorstand',
            'implies_schuetzenrat',
            'is_council',
        ]);
    }

    public function render()
    {
        return view('livewire.admin.settings.board-functions', [
            'functions' => BoardFunction::query()
                ->withCount(['assignments as current_holders_count' => fn ($query) => $query->whereNull('date_to')])
                ->orderBy('sort_order')
                ->orderBy('boardFunctionID')
                ->get(),
            'councilName' => BoardFunction::where('is_council', true)->value('name'),
        ])
            ->layout('layouts.app', [
                'title' => 'Vorstandsfunktionen | VEMA',
                'heading' => 'Einstellungen',
            ]);
    }
}
