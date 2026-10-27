<?php

namespace App\Services;

use App\Enums\FederationMembershipStatus;
use App\Enums\TenantStatus;
use App\Mail\FederationInvitationMail;
use App\Models\FederationMembership;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Verbindungen zwischen Verband und Verein: Der Verband lädt ein, der
 * Verein stimmt zu (oder lehnt ab) und kann jederzeit wieder austreten.
 * Jeder Schritt steht im Verlauf beider Mandanten (Nachweis der Zustimmung).
 */
class FederationMemberships
{
    /**
     * Vereine der Plattform, die der Verband einladen kann.
     *
     * @return Collection<int, Tenant>
     */
    public function invitableClubs(Tenant $federation, string $search, int $limit = 10): Collection
    {
        $search = trim($search);

        if (mb_strlen($search) < 2) {
            return new Collection;
        }

        $connected = $federation->clubMemberships()
            ->whereIn('status', [FederationMembershipStatus::Invited, FederationMembershipStatus::Active])
            ->pluck('club_id');

        return Tenant::query()
            ->where('status', TenantStatus::Active)
            ->whereNotNull('provisioned_at')
            ->whereKeyNot($federation->getKey())
            ->whereNotIn('id', $connected)
            ->where(fn ($query) => $query
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('slug', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * Einladung an einen Verein; nach Ablehnung oder Austritt erneut möglich.
     */
    public function invite(Tenant $federation, Tenant $club, string $invitedBy): FederationMembership
    {
        if (! $federation->isFederation()) {
            throw new InvalidArgumentException('Nur Verbände mit Verbandszentrale können Vereine einladen.');
        }

        if ($club->is($federation) || $club->status !== TenantStatus::Active || $club->provisioned_at === null) {
            throw new InvalidArgumentException('Dieser Verein kann nicht eingeladen werden.');
        }

        $membership = FederationMembership::query()->firstOrNew([
            'federation_id' => $federation->getKey(),
            'club_id' => $club->getKey(),
        ]);

        if (in_array($membership->status, [FederationMembershipStatus::Invited, FederationMembershipStatus::Active], true)) {
            throw new InvalidArgumentException($club->name.' ist bereits eingeladen oder Mitglied.');
        }

        $membership->fill([
            'status' => FederationMembershipStatus::Invited,
            'invited_at' => now(),
            'responded_at' => null,
        ])->save();

        $this->record($membership, 'Einladung in den Verband', $invitedBy);

        if (filled($club->contact_email)) {
            Mail::to($club->contact_email)->send(new FederationInvitationMail($federation, $club));
        }

        return $membership;
    }

    public function accept(FederationMembership $membership, string $acceptedBy): void
    {
        $this->respond($membership, FederationMembershipStatus::Active, 'Einladung in den Verband angenommen', $acceptedBy);
    }

    public function decline(FederationMembership $membership, string $declinedBy): void
    {
        $this->respond($membership, FederationMembershipStatus::Declined, 'Einladung in den Verband abgelehnt', $declinedBy);
    }

    /**
     * Der Verein tritt aus; der Verband sieht ab sofort nichts mehr.
     */
    public function leave(FederationMembership $membership, string $leftBy): void
    {
        if ($membership->status !== FederationMembershipStatus::Active) {
            throw new InvalidArgumentException('Der Verein ist nicht Mitglied dieses Verbands.');
        }

        $membership->update(['status' => FederationMembershipStatus::Left, 'responded_at' => now()]);

        $this->record($membership, 'Austritt aus dem Verband', $leftBy);
    }

    /**
     * Der Verband zieht eine Einladung zurück oder nimmt einen Verein heraus
     * (ebenso der Plattform-Admin).
     */
    public function remove(FederationMembership $membership, string $removedBy): void
    {
        $action = $membership->status === FederationMembershipStatus::Invited
            ? 'Einladung in den Verband zurückgezogen'
            : 'Verbindung zum Verband gelöst';

        $this->record($membership, $action, $removedBy);

        $membership->delete();
    }

    private function respond(FederationMembership $membership, FederationMembershipStatus $status, string $action, string $by): void
    {
        if ($membership->status !== FederationMembershipStatus::Invited) {
            throw new InvalidArgumentException('Diese Einladung ist nicht mehr offen.');
        }

        $membership->update(['status' => $status, 'responded_at' => now()]);

        $this->record($membership, $action, $by);
    }

    private function record(FederationMembership $membership, string $action, string $by): void
    {
        $membership->loadMissing(['federation', 'club']);

        $membership->federation->recordEvent($action.': '.$membership->club->name, ['von' => $by]);
        $membership->club->recordEvent($action.': '.$membership->federation->name, ['von' => $by]);
    }
}
