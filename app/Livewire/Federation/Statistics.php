<?php

namespace App\Livewire\Federation;

use App\Exports\FederationStatisticsExport;
use App\Models\ClubStatistic;
use App\Models\FederationMembership;
use App\Models\Tenant;
use App\Services\ClubStatistics;
use App\Services\TenantManager;
use Illuminate\Support\Collection;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Verbandszentrale: Kennzahlen der Mitgliedsvereine (Stand des letzten
 * Abgleichs, nachts bzw. beim Annehmen der Einladung).
 */
class Statistics extends Component
{
    public function mount(): void
    {
        $this->federation();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->rows()->map(fn (array $row) => [
            'club' => $row['club']->name,
            'collected_at' => $row['statistic']?->collected_at->format('d.m.Y H:i'),
            'data' => $row['statistic']?->data,
        ])->values()->all();

        return Excel::download(new FederationStatisticsExport($rows), 'mitgliedsvereine-'.now()->format('Y-m-d').'.xlsx');
    }

    /**
     * Aktive Mitgliedsvereine mit ihrem letzten Stand.
     *
     * @return Collection<int, array{club: Tenant, statistic: ?ClubStatistic}>
     */
    private function rows(): Collection
    {
        $memberships = $this->federation()->clubMemberships()->active()->with('club')->get();
        $statistics = ClubStatistic::query()->whereIn('tenant_id', $memberships->pluck('club_id'))->get()->keyBy('tenant_id');

        return $memberships
            ->map(fn (FederationMembership $membership) => [
                'club' => $membership->club,
                'statistic' => $statistics->get($membership->club_id),
            ])
            ->sortBy(fn (array $row) => mb_strtolower($row['club']->name))
            ->values();
    }

    private function federation(): Tenant
    {
        $tenant = app(TenantManager::class)->current();

        abort_unless($tenant?->isFederation(), 404);

        return $tenant;
    }

    public function render()
    {
        $rows = $this->rows();

        $totals = collect(array_keys(ClubStatistics::COLUMNS))
            ->mapWithKeys(fn (string $key) => [$key => $rows->sum(fn (array $row) => $row['statistic']?->data[$key] ?? 0)])
            ->all();

        return view('livewire.federation.statistics', [
            'rows' => $rows,
            'totals' => $totals,
            'columns' => ClubStatistics::COLUMNS,
        ])
            ->layout('layouts.app', [
                'title' => 'Statistik | Verbandszentrale | VEMA',
                'heading' => 'Verbandszentrale',
            ]);
    }
}
