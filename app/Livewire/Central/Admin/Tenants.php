<?php

namespace App\Livewire\Central\Admin;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Übersicht aller Vereine der Plattform.
 */
class Tenants extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->validateOnly('status', ['status' => ['nullable', Rule::enum(TenantStatus::class)]]);
        $this->resetPage();
    }

    public function render()
    {
        $tenants = Tenant::query()
            ->when($this->search !== '', function ($query) {
                $term = '%'.trim($this->search).'%';

                $query->where(fn ($query) => $query
                    ->where('name', 'like', $term)
                    ->orWhere('slug', 'like', $term)
                    ->orWhere('contact_email', 'like', $term));
            })
            ->when(TenantStatus::tryFrom($this->status), fn ($query, TenantStatus $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('livewire.central.admin.tenants', [
            'tenants' => $tenants,
            'statuses' => TenantStatus::cases(),
            'counts' => Tenant::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ])->layout('layouts.platform-admin', [
            'title' => 'Vereine | Plattform-Admin',
        ]);
    }
}
