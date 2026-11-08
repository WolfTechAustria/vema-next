<?php

namespace App\Livewire\Members;

use App\Enums\MemberFieldType;
use App\Models\Member;
use App\Models\MemberField;
use App\Services\MemberFields;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

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

    public function render()
    {
        return view('livewire.members.index', [
            'members' => $this->filteredQuery()->paginate(25),
            'filterableFields' => app(MemberFields::class)->fields(),
            'filterField' => $this->filterField(),
        ])->layout('layouts.app', [
            'title' => 'Mitglieder | VEMA',
            'heading' => 'Mitglieder',
        ]);
    }
}
