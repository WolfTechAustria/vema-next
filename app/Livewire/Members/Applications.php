<?php

namespace App\Livewire\Members;

use App\Enums\MemberApplicationStatus;
use App\Models\MemberApplication;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Online eingereichte Mitgliedsanmeldungen, die auf die Freigabe warten
 * (bzw. bereits bearbeitet wurden).
 */
class Applications extends Component
{
    use WithPagination;

    #[Url(as: 'status')]
    public string $statusFilter = 'pending';

    #[Url(as: 'suche')]
    public string $search = '';

    public function updatedStatusFilter(): void
    {
        $this->validateOnly('statusFilter', [
            'statusFilter' => ['required', Rule::in(['all', ...array_column(MemberApplicationStatus::cases(), 'value')])],
        ]);

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $search = trim($this->search);

        $applications = MemberApplication::query()
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('surname', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->paginate(25);

        return view('livewire.members.applications', [
            'applications' => $applications,
            'statuses' => MemberApplicationStatus::cases(),
            'pendingCount' => MemberApplication::query()->pending()->count(),
        ])->layout('layouts.app', [
            'title' => 'Online-Anmeldungen | VEMA',
            'heading' => 'Mitglieder',
        ]);
    }
}
