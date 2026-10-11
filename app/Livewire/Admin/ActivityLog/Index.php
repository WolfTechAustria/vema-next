<?php

namespace App\Livewire\Admin\ActivityLog;

use App\Models\ActivityLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $filter = 'all';

    /**
     * Bereiche des Filters: Präfixe der Aktionsnamen (z. B. "fee." für
     * alle Beitragsereignisse) und auszunehmende Präfixe.
     *
     * @var array<string, array{label: string, include: list<string>, exclude?: list<string>}>
     */
    public const AREAS = [
        'login' => ['label' => 'Anmeldungen', 'include' => ['user.login', 'user.logout', 'member.login', 'member.logout']],
        'member' => ['label' => 'Mitglieder', 'include' => ['member.'], 'exclude' => ['member.login', 'member.logout']],
        'portal' => ['label' => 'Mitgliederbereich', 'include' => ['portal.']],
        'event' => ['label' => 'Termine', 'include' => ['event.']],
        'duty' => ['label' => 'Dienstplan', 'include' => ['duty.', 'skill.']],
        'fee' => ['label' => 'Mitgliedsbeiträge', 'include' => ['fee.']],
        'communication' => ['label' => 'Rundschreiben, Gruppen & Kontakte', 'include' => ['circular.', 'group.', 'contact.', 'template.']],
        'cash-book' => ['label' => 'Kassabuch', 'include' => ['cash-book.']],
        'invoice' => ['label' => 'Rechnungen', 'include' => ['invoice.']],
        'user' => ['label' => 'Benutzerverwaltung', 'include' => ['user.'], 'exclude' => ['user.login', 'user.logout']],
        'settings' => ['label' => 'Einstellungen & Testmodus', 'include' => ['settings.', 'demo.']],
    ];

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $area = self::AREAS[$this->filter] ?? null;

        $entries = ActivityLog::query()
            ->with(['user', 'memberAccount'])
            ->when($area, function (Builder $query) use ($area) {
                $query->where(function (Builder $query) use ($area) {
                    foreach ($area['include'] as $prefix) {
                        $query->orWhere('action', 'like', $prefix.'%');
                    }
                });

                foreach ($area['exclude'] ?? [] as $prefix) {
                    $query->where('action', 'not like', $prefix.'%');
                }
            })
            ->orderByDesc('created_at')
            ->orderByDesc('activityLogID')
            ->paginate(30);

        return view('livewire.admin.activity-log.index', [
            'entries' => $entries,
            'areas' => self::AREAS,
        ])->layout('layouts.app', [
            'title' => 'Aktivitätsprotokoll | VEMA',
            'heading' => 'Aktivitätsprotokoll',
        ]);
    }
}
