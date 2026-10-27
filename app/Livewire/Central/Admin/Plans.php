<?php

namespace App\Livewire\Central\Admin;

use App\Enums\Feature;
use App\Enums\Limit;
use App\Enums\Plan;
use App\Services\PlanCatalog;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * Pakete im Plattform-Admin: Funktionen und Limits je Paket einstellen.
 * Gilt sofort für alle Vereine mit diesem Paket (außer individuell angepasst).
 */
class Plans extends Component
{
    /**
     * @var array<string, array<string, bool>> Paket => Funktion => enthalten
     */
    public array $features = [];

    /**
     * @var array<string, array<string, string>> Paket => Limit => Wert ('' = unbegrenzt)
     */
    public array $limits = [];

    public function mount(PlanCatalog $catalog): void
    {
        $this->fillForm($catalog);
    }

    private function fillForm(PlanCatalog $catalog): void
    {
        foreach (Plan::cases() as $plan) {
            foreach (Feature::cases() as $feature) {
                $this->features[$plan->value][$feature->value] = in_array($feature, $catalog->features($plan), true);
            }

            foreach (Limit::cases() as $limit) {
                $this->limits[$plan->value][$limit->value] = (string) ($catalog->limit($plan, $limit) ?? '');
            }
        }
    }

    public function save(PlanCatalog $catalog): void
    {
        $rules = [];

        foreach (Plan::cases() as $plan) {
            foreach (Limit::cases() as $limit) {
                $rules["limits.{$plan->value}.{$limit->value}"] = ['nullable', 'integer', 'min:1', 'max:100000000'];
            }
        }

        $this->validate($rules, [
            'limits.*.*.integer' => 'Bitte eine ganze Zahl eingeben (leer = unbegrenzt).',
            'limits.*.*.min' => 'Mindestens 1 (leer = unbegrenzt).',
        ]);

        $changed = [];

        foreach (Plan::cases() as $plan) {
            $features = array_values(array_filter(
                Feature::cases(),
                fn (Feature $feature) => (bool) ($this->features[$plan->value][$feature->value] ?? false),
            ));

            $limits = [];

            foreach (Limit::cases() as $limit) {
                $value = $this->limits[$plan->value][$limit->value] ?? '';
                $limits[$limit->value] = $value === '' ? null : (int) $value;
            }

            if (! $this->differs($catalog, $plan, $features, $limits)) {
                continue;
            }

            $catalog->save($plan, $features, $limits);
            $changed[] = $plan->label();
        }

        if ($changed !== []) {
            Log::info('[Plattform-Admin] Pakete geändert', [
                'admin' => auth('platform')->user()?->email,
                'plans' => $changed,
            ]);
        }

        $this->fillForm($catalog);

        session()->flash('success', $changed === [] ? 'Keine Änderungen.' : 'Gespeichert: '.implode(', ', $changed).'.');
    }

    public function resetPlan(string $plan, PlanCatalog $catalog): void
    {
        $plan = Plan::from($plan);

        $catalog->reset($plan);
        $this->fillForm($catalog);

        Log::info('[Plattform-Admin] Paket auf Standard zurückgesetzt', [
            'admin' => auth('platform')->user()?->email,
            'plan' => $plan->value,
        ]);

        session()->flash('success', "{$plan->label()} gilt wieder laut Preisliste.");
    }

    /**
     * @param  array<int, Feature>  $features
     * @param  array<string, int|null>  $limits
     */
    private function differs(PlanCatalog $catalog, Plan $plan, array $features, array $limits): bool
    {
        if ($features !== $catalog->features($plan)) {
            return true;
        }

        foreach (Limit::cases() as $limit) {
            if ($limits[$limit->value] !== $catalog->limit($plan, $limit)) {
                return true;
            }
        }

        return false;
    }

    public function render(PlanCatalog $catalog)
    {
        return view('livewire.central.admin.plans', [
            'plans' => Plan::cases(),
            'featureCases' => Feature::cases(),
            'limitCases' => Limit::cases(),
            'customized' => collect(Plan::cases())
                ->filter(fn (Plan $plan) => $catalog->isCustomized($plan))
                ->map(fn (Plan $plan) => $plan->value)
                ->values()
                ->all(),
        ])->layout('layouts.platform-admin', [
            'title' => 'Pakete | Plattform-Admin',
        ]);
    }
}
