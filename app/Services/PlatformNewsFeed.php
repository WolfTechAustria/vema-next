<?php

namespace App\Services;

use App\Models\PlatformNews;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Neuigkeiten zu VEMA in der Vereinsverwaltung: nur auf der Plattform und
 * nur für Admins des Vereins, mit „gelesen bis“ je Benutzer.
 */
class PlatformNewsFeed
{
    public function __construct(private TenantManager $tenantManager) {}

    /**
     * Darf der Benutzer die Neuigkeiten sehen? (Plattformbetrieb, Admin)
     */
    public function isAvailableFor(?User $user): bool
    {
        return $this->tenantManager->current() !== null
            && (bool) $user?->hasRole('admin');
    }

    /**
     * @return Collection<int, PlatformNews>
     */
    public function news(): Collection
    {
        return PlatformNews::query()
            ->visibleTo($this->tenantManager->current())
            ->latest('published_at')
            ->get();
    }

    public function unreadCount(?User $user): int
    {
        if (! $this->isAvailableFor($user)) {
            return 0;
        }

        $seenAt = $this->seenAt($user);

        return PlatformNews::query()
            ->visibleTo($this->tenantManager->current())
            ->when($seenAt, fn ($query) => $query->where('published_at', '>', $seenAt))
            ->count();
    }

    public function seenAt(User $user): ?Carbon
    {
        $seenAt = DB::connection('landlord')->table('platform_news_seen')
            ->where('tenant_id', $this->tenant()->id)
            ->where('user_id', $user->id)
            ->value('seen_at');

        return $seenAt ? Carbon::parse($seenAt) : null;
    }

    public function markSeen(User $user): void
    {
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
