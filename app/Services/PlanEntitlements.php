<?php

namespace App\Services;

use App\Enums\Feature;
use App\Enums\Plan;
use App\Models\Member;
use App\Models\User;

/**
 * Was der aktuelle Verein laut Paket darf (Funktionen, Limits).
 *
 * Im Modus "single" (eigene Installation, z. B. Angerberg) gibt es keine
 * Pakete — dort ist alles erlaubt und unbegrenzt.
 */
class PlanEntitlements
{
    public function __construct(private TenantManager $tenantManager) {}

    /**
     * Geltendes Paket des aktuellen Vereins; null = keine Einschränkungen.
     */
    public function plan(): ?Plan
    {
        return $this->tenantManager->current()?->effectivePlan();
    }

    public function allows(Feature $feature): bool
    {
        return $this->plan()?->includes($feature) ?? true;
    }

    public function memberLimit(): ?int
    {
        return $this->plan()?->memberLimit();
    }

    public function staffLimit(): ?int
    {
        return $this->plan()?->staffLimit();
    }

    public function activeMemberCount(): int
    {
        return Member::query()->active()->count();
    }

    public function activeStaffCount(): int
    {
        return User::query()->where('enabled', true)->count();
    }

    /**
     * Darf (noch) ein weiteres aktives Mitglied dazukommen?
     */
    public function canAddActiveMember(): bool
    {
        $limit = $this->memberLimit();

        return $limit === null || $this->activeMemberCount() < $limit;
    }

    /**
     * Darf (noch) ein weiterer aktiver Vorstandszugang dazukommen?
     */
    public function canAddActiveStaff(): bool
    {
        $limit = $this->staffLimit();

        return $limit === null || $this->activeStaffCount() < $limit;
    }

    public function memberLimitMessage(): string
    {
        return 'Euer Paket „'.$this->plan()?->label().'“ umfasst bis zu '.$this->memberLimit()
            .' aktive Mitglieder. Für mehr Mitglieder bitte ein größeres Paket buchen.';
    }

    public function staffLimitMessage(): string
    {
        $limit = $this->staffLimit();

        return 'Euer Paket „'.$this->plan()?->label().'“ umfasst '.$limit.' '
            .($limit === 1 ? 'Vorstandszugang' : 'Vorstandszugänge')
            .'. Für weitere Zugänge bitte ein größeres Paket buchen.';
    }

    public function featureMessage(Feature $feature): string
    {
        return '„'.$feature->label().'“ ist in eurem Paket „'.$this->plan()?->label()
            .'“ nicht enthalten (ab Paket „'.Plan::lowestWith($feature)->label().'“).';
    }
}
