<?php

namespace App\Services;

use App\Enums\Feature;
use App\Enums\Limit;
use App\Enums\Plan;
use App\Models\PlanSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Funktionen und Limits der Pakete: wie im Plattform-Admin eingestellt,
 * sonst die Standardwerte laut Preisliste (Plan::default…).
 */
class PlanCatalog
{
    /**
     * @var Collection<string, PlanSetting>|null
     */
    private ?Collection $settings = null;

    public function __construct(private TenantManager $tenantManager) {}

    /**
     * @return array<int, Feature>
     */
    public function features(Plan $plan): array
    {
        $setting = $this->setting($plan);

        if ($setting === null) {
            return $plan->defaultFeatures();
        }

        // Reihenfolge wie Feature::cases(); unbekannte Werte ignorieren.
        return array_values(array_filter(
            Feature::cases(),
            fn (Feature $feature) => in_array($feature->value, $setting->features, true),
        ));
    }

    public function limit(Plan $plan, Limit $limit): ?int
    {
        $setting = $this->setting($plan);

        return $setting === null
            ? $plan->defaultLimit($limit)
            : $setting->getAttribute($limit->value);
    }

    public function isCustomized(Plan $plan): bool
    {
        return $this->setting($plan) !== null;
    }

    /**
     * @param  array<int, Feature>  $features
     * @param  array<string, int|null>  $limits  Schlüssel: Limit-Werte
     */
    public function save(Plan $plan, array $features, array $limits): void
    {
        $attributes = ['features' => array_map(fn (Feature $feature) => $feature->value, $features)];

        foreach (Limit::cases() as $limit) {
            $attributes[$limit->value] = $limits[$limit->value] ?? null;
        }

        PlanSetting::query()->updateOrCreate(['plan' => $plan->value], $attributes);

        $this->settings = null;
    }

    public function reset(Plan $plan): void
    {
        PlanSetting::query()->whereKey($plan->value)->delete();

        $this->settings = null;
    }

    private function setting(Plan $plan): ?PlanSetting
    {
        return $this->settings()->get($plan->value);
    }

    /**
     * @return Collection<string, PlanSetting>
     */
    private function settings(): Collection
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        // Pakete gibt es nur im Plattform-Betrieb.
        if (! $this->tenantManager->isMultiTenant()) {
            return $this->settings = collect();
        }

        try {
            return $this->settings = PlanSetting::query()->get()->keyBy('plan');
        } catch (QueryException) {
            // Zentrale Datenbank noch nicht migriert (z. B. während des Deploys).
            return $this->settings = collect();
        }
    }
}
