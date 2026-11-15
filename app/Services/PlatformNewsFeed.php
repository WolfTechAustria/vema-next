<?php

namespace App\Services;

use App\Models\PlatformNews;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VemaNews;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Neuigkeiten zu VEMA in der Vereinsverwaltung, nur für Admins, mit
 * „gelesen bis“ je Benutzer. Auf der Plattform direkt aus der zentralen
 * Datenbank, in einer eigenen Installation aus dem übernommenen Feed
 * (PlatformNewsPull).
 */
class PlatformNewsFeed
{
    public function __construct(
        private TenantManager $tenantManager,
        private PlatformNewsPull $pull,
    ) {}

    /**
     * Darf der Benutzer die Neuigkeiten sehen? (Admin; Plattform oder eingerichteter Feed)
     */
    public function isAvailableFor(?User $user): bool
    {
        if (! $user?->hasRole('admin')) {
            return false;
        }

        return $this->tenantManager->isMultiTenant()
            ? $this->tenantManager->current() !== null
            : $this->pull->isConfigured();
    }

    /**
     * @return Collection<int, PlatformNews>|Collection<int, VemaNews>
     */
    public function news(): Collection
    {
        if (! $this->tenantManager->isMultiTenant()) {
            return VemaNews::query()->latest('published_at')->get();
        }

        return PlatformNews::query()
            ->visibleTo($this->tenant())
            ->latest('published_at')
            ->get();
    }

    public function unreadCount(?User $user): int
    {
        if (! $this->isAvailableFor($user)) {
            return 0;
        }

        $seenAt = $this->seenAt($user);

        $query = $this->tenantManager->isMultiTenant()
            ? PlatformNews::query()->visibleTo($this->tenant())
            : VemaNews::query();

        return $query
            ->when($seenAt, fn ($query) => $query->where('published_at', '>', $seenAt))
            ->count();
    }

    public function seenAt(User $user): ?Carbon
    {
        $seenAt = $this->tenantManager->isMultiTenant()
            ? DB::connection('landlord')->table('platform_news_seen')
                ->where('tenant_id', $this->tenant()->id)
                ->where('user_id', $user->id)
                ->value('seen_at')
            : $user->getRawOriginal('news_seen_at');

        return $seenAt ? Carbon::parse($seenAt) : null;
    }

    public function markSeen(User $user): void
    {
        if (! $this->tenantManager->isMultiTenant()) {
            $user->forceFill(['news_seen_at' => now()])->saveQuietly();

            return;
        }

        DB::connection('landlord')->table('platform_news_seen')->upsert(
            [['tenant_id' => $this->tenant()->id, 'user_id' => $user->id, 'seen_at' => now()]],
            ['tenant_id', 'user_id'],
            ['seen_at'],
        );
    }

    private function tenant(): Tenant
    {
        return $this->tenantManager->current();
    }
}
