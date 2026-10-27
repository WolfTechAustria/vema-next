<?php

namespace App\Livewire\Federation;

use App\Enums\FederationMembershipStatus;
use App\Models\FederationMembership;
use App\Models\Tenant;
use App\Services\FederationMemberships;
use App\Services\TenantManager;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Verbandszentrale: Mitgliedsvereine einladen und verwalten.
 */
class Clubs extends Component
{
    public string $search = '';

    public function mount(): void
    {
        $this->federation();
    }

    public function invite(int $clubId, FederationMemberships $memberships): void
    {
        $this->authorizeAdmin();

        $club = $memberships->invitableClubs($this->federation(), $this->search, 50)->firstWhere('id', $clubId);

        if ($club === null) {
            session()->flash('error', 'Dieser Verein kann nicht eingeladen werden.');

            return;
        }

        try {
            $memberships->invite($this->federation(), $club, auth()->user()->username);
        } catch (InvalidArgumentException $exception) {
            session()->flash('error', $exception->getMessage());

            return;
        }

        $this->search = '';

        session()->flash('success', $club->name.' wurde eingeladen.');
    }

    public function remove(int $membershipId, FederationMemberships $memberships): void
    {
        $this->authorizeAdmin();

        $membership = $this->federation()->clubMemberships()->with('club')->findOrFail($membershipId);
        $wasInvited = $membership->status === FederationMembershipStatus::Invited;

        $memberships->remove($membership, auth()->user()->username);

        session()->flash('success', $wasInvited
            ? 'Die Einladung an '.$membership->club->name.' wurde zurückgezogen.'
            : $membership->club->name.' wurde aus dem Verband entfernt.');
    }

    private function federation(): Tenant
    {
        $tenant = app(TenantManager::class)->current();

        abort_unless($tenant?->isFederation(), 404);

        return $tenant;
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
    }

    public function render(FederationMemberships $memberships)
    {
        $federation = $this->federation();

        return view('livewire.federation.clubs', [
            'memberships' => $federation->clubMemberships()
                ->with('club')
                ->get()
                ->sortBy([
                    fn (FederationMembership $a, FederationMembership $b) => array_search($a->status, FederationMembershipStatus::cases()) <=> array_search($b->status, FederationMembershipStatus::cases()),
                    fn (FederationMembership $a, FederationMembership $b) => strcasecmp($a->club->name, $b->club->name),
                ]),
            'results' => $memberships->invitableClubs($federation, $this->search),
            'isAdmin' => (bool) auth()->user()?->hasRole('admin'),
        ])
            ->layout('layouts.app', [
                'title' => 'Verbandszentrale | VEMA',
                'heading' => 'Verbandszentrale',
            ]);
    }
}
