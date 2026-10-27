<?php

namespace App\Livewire\Admin\Settings;

use App\Enums\FederationMembershipStatus;
use App\Models\FederationMembership;
use App\Models\Tenant;
use App\Services\ClubStatistics;
use App\Services\DemoMode;
use App\Services\FederationMemberships;
use App\Services\TenantManager;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Verbände des Vereins: Einladungen annehmen oder ablehnen, austreten
 * (nur Plattformbetrieb).
 */
class Federations extends Component
{
    public function mount(): void
    {
        $this->club();
    }

    public function accept(int $membershipId, FederationMemberships $memberships, ClubStatistics $statistics, DemoMode $demoMode): void
    {
        $this->respond($membershipId, function (FederationMembership $membership, string $by) use ($memberships, $statistics, $demoMode) {
            $memberships->accept($membership, $by);

            // Der Verband sieht die Kennzahlen gleich, nicht erst nach dem nächtlichen Abgleich.
            if (! $demoMode->isActive()) {
                $statistics->store($this->club());
            }
        }, 'Ihr seid jetzt Mitglied bei :name.');
    }

    public function decline(int $membershipId, FederationMemberships $memberships): void
    {
        $this->respond($membershipId, fn (FederationMembership $membership, string $by) => $memberships->decline($membership, $by), 'Die Einladung von :name wurde abgelehnt.');
    }

    public function leave(int $membershipId, FederationMemberships $memberships): void
    {
        $this->respond($membershipId, fn (FederationMembership $membership, string $by) => $memberships->leave($membership, $by), 'Ihr seid aus :name ausgetreten.');
    }

    /**
     * @param  callable(FederationMembership, string): void  $action
     */
    private function respond(int $membershipId, callable $action, string $message): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $membership = $this->club()->federationMemberships()->with('federation')->findOrFail($membershipId);

        try {
            $action($membership, auth()->user()->username);
        } catch (InvalidArgumentException $exception) {
            session()->flash('error', $exception->getMessage());

            return;
        }

        session()->flash('success', str_replace(':name', $membership->federation->name, $message));
    }

    private function club(): Tenant
    {
        $tenant = app(TenantManager::class)->current();

        abort_if($tenant === null, 404);

        return $tenant;
    }

    public function render()
    {
        $memberships = $this->club()->federationMemberships()
            ->with('federation')
            ->whereIn('status', [FederationMembershipStatus::Invited, FederationMembershipStatus::Active])
            ->get();

        return view('livewire.admin.settings.federations', [
            'invitations' => $memberships->where('status', FederationMembershipStatus::Invited),
            'federations' => $memberships->where('status', FederationMembershipStatus::Active),
        ])
            ->layout('layouts.app', [
                'title' => 'Verbände | VEMA',
                'heading' => 'Einstellungen',
            ]);
    }
}
