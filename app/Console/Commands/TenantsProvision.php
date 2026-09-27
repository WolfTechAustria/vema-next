<?php

namespace App\Console\Commands;

use App\Mail\Central\TenantWelcomeMail;
use App\Models\Tenant;
use App\Services\TenantManager;
use App\Services\TenantProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TenantsProvision extends Command
{
    protected $signature = 'tenants:provision {slug : Adresse des Vereins}';

    protected $description = 'Richtet einen bestätigten, aber noch nicht eingerichteten Verein ein (z. B. nach einem Fehler)';

    public function handle(TenantManager $tenantManager, TenantProvisioner $provisioner): int
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

        if ($tenant->email_verified_at === null) {
            $this->error('Die E-Mail-Adresse dieses Vereins ist noch nicht bestätigt.');

            return self::FAILURE;
        }

        try {
            $passwordSetupUrl = $provisioner->provision($tenant);
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        Mail::to($tenant->contact_email)->send(new TenantWelcomeMail($tenant->refresh(), $passwordSetupUrl));

        $this->info("{$tenant->name} ist eingerichtet: {$tenant->url()} (Willkommensmail an {$tenant->contact_email} verschickt).");

        return self::SUCCESS;
    }
}
