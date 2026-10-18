<?php

namespace App\Services;

use App\Enums\Feature;
use App\Models\ClubEvent;
use App\Models\HomepageSetting;
use Illuminate\Support\Collection;

/**
 * Öffentliche Vereinstermine für die Homepage (siehe ClubEvent::onHomepage()).
 */
class HomepageEvents
{
    public function __construct(private PlanEntitlements $entitlements) {}

    /**
     * Termine sind im Paket enthalten und auf der Homepage eingeschaltet.
     */
    public function enabled(): bool
    {
        return $this->entitlements->allows(Feature::Events) && HomepageSetting::current()->show_events;
    }

    /**
     * @return Collection<int, ClubEvent>
     */
    public function upcoming(?int $limit = null): Collection
    {
        if (! $this->enabled()) {
            return collect();
        }

        return ClubEvent::query()
            ->onHomepage()
            ->upcoming()
            ->orderBy('starts_at')
            ->when($limit, fn ($query) => $query->limit($limit))
            ->get();
    }

    /**
     * Vergangene Termine der letzten zwölf Monate, neueste zuerst.
     *
     * @return Collection<int, ClubEvent>
     */
    public function past(): Collection
    {
        if (! $this->enabled()) {
            return collect();
        }

        return ClubEvent::query()
            ->onHomepage()
            ->past()
            ->where('starts_at', '>=', now()->subYear()->startOfDay())
            ->orderByDesc('starts_at')
            ->get();
    }
}
