<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\HomepageHosts;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TenantsDomain extends Command
{
    protected $signature = 'tenants:domain
        {slug : Adresse des Vereins}
        {domain : Eigene Domain, z. B. www.mein-verein.at}
        {--homepage : Domain führt zur öffentlichen Vereinshomepage statt zur App}
        {--remove : Domain wieder entfernen}';

    protected $description = 'Ordnet einem Verein eine eigene Domain zu (App oder Vereinshomepage)';

    public function handle(TenantManager $tenantManager): int
    {
        if (! $tenantManager->isMultiTenant()) {
            $this->error('Nur im Modus TENANCY_MODE=multi verfügbar.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->where('slug', $this->argument('slug'))->first();

        if (! $tenant) {
            $this->error('Verein nicht gefunden.');

            return self::FAILURE;
        }

        $domain = strtolower(trim((string) $this->argument('domain')));
        Cache::forget('homepage.host.'.$domain);

        if ($this->option('remove')) {
            $deleted = TenantDomain::query()->where('tenant_id', $tenant->id)->where('domain', $domain)->delete();

            $deleted
                ? $this->info("{$domain} wurde von {$tenant->name} entfernt.")
                : $this->warn("{$domain} ist {$tenant->name} nicht zugeordnet.");

            return self::SUCCESS;
        }

        $existing = TenantDomain::query()->where('domain', $domain)->first();

        if ($existing && $existing->tenant_id !== $tenant->id) {
            $this->error("{$domain} gehört bereits zu einem anderen Verein.");

            return self::FAILURE;
        }

        $purpose = $this->option('homepage') ? HomepageHosts::PURPOSE : 'app';

        TenantDomain::query()->updateOrCreate(
            ['domain' => $domain],
            ['tenant_id' => $tenant->id, 'purpose' => $purpose],
        );

        $this->info("{$domain} führt jetzt zu ".($purpose === 'app' ? 'VEMA' : 'der Homepage')." von {$tenant->name}.");

        return self::SUCCESS;
    }
}
