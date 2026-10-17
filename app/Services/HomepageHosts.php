<?php

namespace App\Services;

use App\Enums\Feature;
use App\Models\HomepageSetting;
use App\Models\TenantDomain;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Erkennt, ob eine Anfrage an die eigene Domain einer Vereinshomepage geht.
 *
 * - Modus "multi": Eintrag in tenant_domains mit purpose = "homepage".
 * - Modus "single": Domain in den Homepage-Einstellungen (auch mit „www.“).
 */
class HomepageHosts
{
    public const PURPOSE = 'homepage';

    private const SINGLE_CACHE_KEY = 'homepage.domain';

    /**
     * Interner Pfad-Präfix der Homepage-Routen (routes/homepage.php).
     */
    public const PATH_PREFIX = 'site';

    private bool $active = false;

    public function __construct(private TenantManager $tenantManager) {}

    public function matches(string $host): bool
    {
        $host = Str::lower($host);

        if ($this->tenantManager->isMultiTenant()) {
            if ($this->tenantManager->isCentralHost($host)) {
                return false;
            }

            return Cache::remember(
                'homepage.host.'.$host,
                now()->addMinutes(5),
                fn () => TenantDomain::query()
                    ->where('domain', $host)
                    ->where('purpose', self::PURPOSE)
                    ->exists()
            );
        }

        $domain = $this->singleDomain();

        return $domain !== '' && in_array($host, [$domain, 'www.'.$domain], true);
    }

    /**
     * Die laufende Anfrage kam über die Homepage-Domain.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    public function markActive(): void
    {
        $this->active = true;
    }

    /**
     * Eingetragener Link der Homepage: interne Pfade wie „/training“ zeigen auf
     * die Homepage — auf der eigenen Domain direkt, in der Vorschau unter /site.
     */
    public static function link(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return url(self::PATH_PREFIX.$url);
        }

        return $url;
    }

    /**
     * Absolute Adresse der Anmeldung zum Mitgliederbereich auf der App-Domain
     * (nicht auf der Homepage-Domain erreichbar); null ohne Mitgliederportal.
     */
    public function memberAreaUrl(): ?string
    {
        if (! app(PlanEntitlements::class)->allows(Feature::MemberPortal)) {
            return null;
        }

        $root = $this->tenantManager->current()?->url() ?? (string) config('app.url');

        return rtrim($root, '/').route('member.login', [], false);
    }

    public function forgetSingleDomain(): void
    {
        Cache::forget(self::SINGLE_CACHE_KEY);
    }

    /**
     * Domain ohne Schema, Pfad und „www.“ — so wird sie gespeichert.
     */
    public static function normalize(?string $domain): ?string
    {
        $domain = Str::lower(trim((string) $domain));
        $domain = (string) preg_replace('#^https?://#', '', $domain);
        $domain = Str::before($domain, '/');
        $domain = (string) preg_replace('/^www\./', '', $domain);

        return $domain === '' ? null : $domain;
    }

    private function singleDomain(): string
    {
        try {
            return Cache::rememberForever(
                self::SINGLE_CACHE_KEY,
                fn () => (string) HomepageSetting::query()->value('domain')
            );
        } catch (Throwable) {
            // Noch nicht migriert (frische Installation).
            return '';
        }
    }
}
