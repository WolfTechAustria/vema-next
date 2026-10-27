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

    /**
     * Schnellfilter für Vereine, um die man sich kümmern sollte.
     */
    #[Url]
    public string $focus = '';

    public const FOCUS_FILTERS = [
        'trial-ending' => 'Testphase endet in 7 Tagen',
        'license-expiring' => 'Lizenz läuft in 30 Tagen ab',
        'license-expired' => 'Lizenz abgelaufen',
        'unverified' => 'E-Mail nicht bestätigt',
        'not-provisioned' => 'Bestätigt, nicht eingerichtet',
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFocus(): void
    {
        $this->validateOnly('focus', ['focus' => ['nullable', Rule::in(array_keys(self::FOCUS_FILTERS))]]);
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
            ->when($this->focus === 'trial-ending', fn ($query) => $query
                ->whereBetween('trial_ends_at', [now(), now()->addDays(7)->endOfDay()]))
            ->when($this->focus === 'license-expiring', fn ($query) => $query
                ->whereBetween('license_valid_until', [now()->toDateString(), now()->addDays(30)->toDateString()]))
            ->when($this->focus === 'license-expired', fn ($query) => $query
                ->where('license_valid_until', '<', now()->toDateString()))
            ->when($this->focus === 'unverified', fn ($query) => $query->whereNull('email_verified_at'))
            ->when($this->focus === 'not-provisioned', fn ($query) => $query
                ->whereNotNull('email_verified_at')
                ->whereNull('provisioned_at'))
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('livewire.central.admin.tenants', [
            'tenants' => $tenants,
            'statuses' => TenantStatus::cases(),
            'focusFilters' => self::FOCUS_FILTERS,
            'counts' => Tenant::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ])->layout('layouts.platform-admin', [
            'title' => 'Vereine | Plattform-Admin',
        ]);
    }
}
