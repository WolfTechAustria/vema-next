<?php

namespace App\Livewire\Admin\ActivityLog;

use App\Models\ActivityLog;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $filter = 'all';

    protected array $filterPrefixes = [
        'login' => ['user.login', 'user.login_failed', 'user.logout', 'member.login', 'member.login_failed', 'member.logout'],
        'member' => ['member.created', 'member.updated'],
        'user' => ['user.created', 'user.enabled', 'user.disabled', 'user.admin_granted', 'user.admin_revoked'],
        'settings' => ['settings.updated'],
    ];

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $entries = ActivityLog::query()
            ->with(['user', 'memberAccount'])
            ->when(
                $this->filter !== 'all' && isset($this->filterPrefixes[$this->filter]),
                fn ($query) => $query->whereIn('action', $this->filterPrefixes[$this->filter])
            )
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('livewire.admin.activity-log.index', [
            'entries' => $entries,
        ])->layout('layouts.app', [
            'title' => 'Aktivitätsprotokoll | VEMA',
            'heading' => 'Aktivitätsprotokoll',
        ]);
    }
}
