<?php

namespace App\Services;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mandantenfähigkeit (TENANCY_MODE=multi): schaltet Datenbank, Dateien,
 * Cache, Mail-Absender, Testmodus und URLs auf einen Verein um. Nach
 * demselben Prinzip wie der Testmodus (DemoMode): Umgeschaltet wird die
 * Default-Connection — kein Vereins-Model legt seine Connection selbst fest.
 *
 * Im Modus "single" wird nichts umgeschaltet.
 */
class TenantManager
{
    private ?Tenant $current = null;

    /**
     * Konfiguration vor dem ersten Umschalten, für restore().
     *
     * @var array<string, mixed>|null
     */
    private ?array $original = null;

    public function isMultiTenant(): bool
    {
        return config('tenancy.mode') === 'multi';
    }

    public function current(): ?Tenant
    {
        return $this->current;
    }

    public function landlordConnection(): string
    {
        return config('tenancy.landlord_connection');
    }

    public function tenantConnection(): string
    {
        return config('tenancy.tenant_connection');
    }

    /**
     * Beim Booten im Modus "multi": Solange kein Verein aktiv ist, zeigt die
     * Default-Connection auf die zentrale Datenbank — eine Abfrage vor der
     * Vereinserkennung kann so nie in einer Vereinsdatenbank landen.
     * Session, Cache und Queue bleiben dauerhaft auf der zentralen Datenbank.
     */
    public function bootLandlord(): void
    {
        $landlord = $this->landlordConnection();

        foreach (['session.connection', 'cache.stores.database.connection', 'cache.stores.database.lock_connection', 'queue.connections.database.connection', 'queue.failed.database'] as $key) {
            config([$key => $landlord]);
        }

        DB::setDefaultConnection($landlord);
    }

    /**
     * Verein anhand des Hosts: Subdomain der Plattform oder eigene Domain.
     */
    public function findByHost(string $host): ?Tenant
    {
        $host = Str::lower($host);
        $suffix = '.'.Str::lower(config('tenancy.tenant_domain'));

        if (Str::endsWith($host, $suffix)) {
            $slug = Str::beforeLast($host, $suffix);

            if ($slug !== '' && ! str_contains($slug, '.')) {
                return Tenant::query()->where('slug', $slug)->first();
            }
        }

        return Tenant::query()
            ->whereHas('domains', fn ($query) => $query->where('domain', $host))
            ->first();
    }

    public function isCentralHost(string $host): bool
    {
        return Str::lower($host) === Str::lower(config('tenancy.platform_host'));
    }

    public function apply(Tenant $tenant): void
    {
        $this->original ??= [
            'default_connection' => DB::getDefaultConnection(),
            'local_root' => config('filesystems.disks.local.root'),
            'branding_root' => config('filesystems.disks.branding.root'),
            'cache_prefix' => config('cache.prefix'),
            'demo_live_connection' => config('demo.live_connection'),
            'demo_database' => config('database.connections.demo.database'),
            'demo_storage_root' => config('demo.storage_root'),
        ];

        $tenantConnection = $this->tenantConnection();

        config(["database.connections.{$tenantConnection}.database" => $tenant->database]);
        DB::purge($tenantConnection);
        DB::setDefaultConnection($tenantConnection);

        config([
            'filesystems.disks.local.root' => $tenant->storagePath('private'),
            'filesystems.disks.branding.root' => $tenant->storagePath('branding'),
            'cache.prefix' => $this->original['cache_prefix'].'tenant_'.$tenant->getKey().'_',
            // Testmodus je Verein: eigene Kopie der Vereinsdatenbank.
            'demo.live_connection' => $tenantConnection,
            'database.connections.demo.database' => $tenant->demoDatabase(),
            'demo.storage_root' => $tenant->storagePath('demo'),
        ]);

        $this->current = $tenant;

        $this->refreshServices();
    }

    /**
     * Zurück zum Zustand vor apply() (Default-Connection: zentrale DB).
     */
    public function restore(): void
    {
        if ($this->original === null) {
            return;
        }

        DB::setDefaultConnection($this->original['default_connection']);
        DB::purge($this->tenantConnection());

        config([
            "database.connections.{$this->tenantConnection()}.database" => null,
            'filesystems.disks.local.root' => $this->original['local_root'],
            'filesystems.disks.branding.root' => $this->original['branding_root'],
            'cache.prefix' => $this->original['cache_prefix'],
            'demo.live_connection' => $this->original['demo_live_connection'],
            'database.connections.demo.database' => $this->original['demo_database'],
            'demo.storage_root' => $this->original['demo_storage_root'],
        ]);

        $this->current = null;

        $this->refreshServices();
    }

    /**
     * Führt $callback für jeden freigeschalteten Verein aus (Scheduler,
     * Artisan). Im Modus "single" genau einmal ohne Verein.
     *
     * @param  Closure(?Tenant): void  $callback
     */
    public function eachAccessible(Closure $callback): void
    {
        if (! $this->isMultiTenant()) {
            $callback(null);

            return;
        }

        $tenants = Tenant::query()
            ->where('status', TenantStatus::Active)
            ->orderBy('id')
            ->get()
            ->filter(fn (Tenant $tenant) => $tenant->isAccessible());

        foreach ($tenants as $tenant) {
            $this->runFor($tenant, $callback);
        }
    }

    /**
     * @template TResult
     *
     * @param  Closure(Tenant): TResult  $callback
     * @return TResult
     */
    public function runFor(Tenant $tenant, Closure $callback): mixed
    {
        $this->apply($tenant);

        // Links in Mails aus dem Scheduler zeigen auf die Subdomain des Vereins.
        URL::forceRootUrl($tenant->url());
        URL::forceScheme(config('tenancy.scheme'));

        try {
            return $callback($tenant);
        } finally {
            URL::forceRootUrl(null);
            URL::forceScheme(null);
            $this->restore();
        }
    }

    /**
     * Dienste, die Konfiguration beim ersten Zugriff einlesen, neu aufbauen.
     */
    private function refreshServices(): void
    {
        // Der Password-Broker hält die DB-Verbindung ab dem ersten Zugriff fest.
        app()->forgetInstance('auth.password');
        Password::clearResolvedInstance('auth.password');

        Storage::forgetDisk(['local', 'branding']);
        DB::purge('demo');
        Cache::forgetDriver(config('cache.default'));

        // Rollen/Rechte: Cache-Store mit neuem Präfix, geladene Rollen verwerfen.
        if (app()->resolved(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->initializeCache();
        }

        if (app()->resolved('mail.manager')) {
            app(ClubMailSender::class)->apply();
            Mail::forgetMailers();
        }
    }
}
