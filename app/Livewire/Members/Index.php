<?php

namespace App\Livewire\Members;

use App\Models\Member;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = 'active';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $members = Member::query()
            ->when(
                $this->search,
                function ($query) {
                    $query->where(function ($query) {
                        $query
                            ->where('name', 'like', '%' . $this->search . '%')
                            ->orWhere('surname', 'like', '%' . $this->search . '%');
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
            ->orderBy('surname')
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.members.index', [
            'members' => $members,
        ])->layout('layouts.app', [
            'title' => 'Mitglieder | VEMA',
            'heading' => 'Mitglieder',
        ]);
    }
}
