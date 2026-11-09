<?php

namespace App\Livewire\Members;

use App\Enums\MemberFieldType;
use App\Exports\MembersExport;
use App\Models\Honor;
use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\MemberField;
use App\Services\ActivityLogger;
use App\Services\MemberApplicationSettings;
use App\Services\MemberColumns;
use App\Services\MemberFields;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = 'active';

    /** Filter nach einem eigenen Mitgliederfeld (fieldID) */
    #[Url(as: 'feld')]
    public string $fieldFilter = '';

    #[Url(as: 'wert')]
    public string $fieldFilterValue = '';

    /** Filter nach einer Ehrung (honorID) */
    #[Url(as: 'ehrung')]
    public string $honorFilter = '';

    public bool $showColumnSettings = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFieldFilter(): void
    {
        $this->fieldFilterValue = '';
        $this->resetPage();
    }

    public function updatingFieldFilterValue(): void
    {
        $this->resetPage();
    }

    public function updatingHonorFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Spalte ein- oder ausblenden (gespeichert für den Benutzer).
     */
    public function toggleColumn(string $key, MemberColumns $columns): void
    {
        $visible = $columns->columnsFor(auth()->user());

        $columns->save(auth()->user(), in_array($key, $visible, true)
            ? array_values(array_diff($visible, [$key]))
            : [...$visible, $key]);
    }

    public function moveColumn(string $key, int $direction, MemberColumns $columns): void
    {
        $visible = $columns->columnsFor(auth()->user());
        $index = array_search($key, $visible, true);
        $target = $index === false ? null : $index + ($direction < 0 ? -1 : 1);

        if ($target === null || ! isset($visible[$target])) {
            return;
        }

        [$visible[$index], $visible[$target]] = [$visible[$target], $visible[$index]];

        $columns->save(auth()->user(), $visible);
    }

    public function resetColumns(MemberColumns $columns): void
    {
        $columns->reset(auth()->user());
    }

    /**
     * Eigene Auswahl als Standard für alle ohne eigene Auswahl (nur Admin).
     */
    public function saveColumnsAsClubDefault(MemberColumns $columns): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $columns->saveClubDefault($columns->columnsFor(auth()->user()));

        ActivityLogger::log(
            'member.columns_default_saved',
            'Spalten der Mitgliederübersicht wurden als Vereinsstandard gespeichert: '
                .collect($columns->columnsFor(auth()->user()))->map(fn (string $key) => $columns->catalog()[$key])->join(', ').'.',
        );

        session()->flash('success', 'Spaltenauswahl als Vereinsstandard gespeichert.');
    }

    /**
     * Gefilterte Mitglieder mit allen Spalten als Excel-Datei.
     */
    public function export(MemberColumns $columns): BinaryFileResponse
    {
        $members = $this->filteredQuery()
            ->with(['city', ...$columns->relationsFor(array_keys($columns->catalog()))])
            ->get();

        return Excel::download(new MembersExport($members), 'mitglieder-'.now()->format('Y-m-d').'.xlsx');
    }

    /**
     * Mitglieder nach Suche und Filtern (auch für den Export).
     *
     * @return Builder<Member>
     */
    private function filteredQuery(): Builder
    {
        return Member::query()
            ->when(
                $this->search,
                function ($query) {
                    $query->where(function ($query) {
                        $query
                            ->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('surname', 'like', '%'.$this->search.'%');
                    });
                }
            )
            ->when(
                $this->status === 'active',
                fn ($query) => $query->where('active', 1)
            )
            ->when(
                $this->status === 'inactive',
                fn ($query) => $query->where('active', 0)
            )
            ->tap(fn (Builder $query) => $this->applyFieldFilter($query))
            ->when(
                $this->honorFilter !== '',
                fn ($query) => $query->whereHas('honors', fn ($honors) => $honors->where('honorID', (int) $this->honorFilter))
            )
            ->orderBy('surname')
            ->orderBy('name');
    }

    private function applyFieldFilter(Builder $query): void
    {
        $field = $this->filterField();

        if ($field === null || $this->fieldFilterValue === '') {
            return;
        }

        $matches = fn ($values) => match ($field->type) {
            MemberFieldType::Boolean => $values->where('value', '1'),
            MemberFieldType::Select => $values->where('value', $this->fieldFilterValue),
            default => $values->where('value', 'like', '%'.$this->fieldFilterValue.'%'),
        };

        // „Nein“ heißt bei Ja/Nein-Feldern: kein Wert gespeichert.
        if ($field->type === MemberFieldType::Boolean && $this->fieldFilterValue === '0') {
            $query->whereDoesntHave('fieldValues', fn ($values) => $values->where('fieldID', $field->fieldID)->where('value', '1'));

            return;
        }

        $query->whereHas('fieldValues', fn ($values) => $matches($values->where('fieldID', $field->fieldID)));
    }

    private function filterField(): ?MemberField
    {
        if ($this->fieldFilter === '') {
            return null;
        }

        return app(MemberFields::class)->fields()->firstWhere('fieldID', (int) $this->fieldFilter);
    }

    public function render(MemberColumns $columns)
    {
        $visibleColumns = $columns->columnsFor(auth()->user());
        $catalog = $columns->catalog();

        return view('livewire.members.index', [
            'members' => $this->filteredQuery()->with($columns->relationsFor($visibleColumns))->paginate(25),
            'columns' => $columns,
            'visibleColumns' => $visibleColumns,
            'columnLabels' => $catalog,
            // Erst die sichtbaren in ihrer Reihenfolge, dann die übrigen.
            'columnChoices' => [...$visibleColumns, ...array_values(array_diff(array_keys($catalog), $visibleColumns))],
            'hasPersonalColumns' => $columns->hasPersonalColumns(auth()->user()),
            'pendingApplications' => MemberApplication::query()->pending()->count(),
            'applicationsEnabled' => app(MemberApplicationSettings::class)->isEnabled(),
            'isAdmin' => (bool) auth()->user()?->hasRole('admin'),
            'filterableFields' => app(MemberFields::class)->fields(),
            'filterField' => $this->filterField(),
            'honorOptions' => Honor::query()->orderBy('name')->get(['honorID', 'name']),
        ])->layout('layouts.app', [
            'title' => 'Mitglieder | VEMA',
            'heading' => 'Mitglieder',
        ]);
    }
}
