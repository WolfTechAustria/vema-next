<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PlatformAdminCreate extends Command
{
    protected $signature = 'platform:admin-create
        {email : E-Mail-Adresse (Login)}
        {--name= : Anzeigename}';

    protected $description = 'Legt einen Plattform-Admin an oder setzt dessen Passwort neu (TENANCY_MODE=multi)';

    public function handle(TenantManager $tenantManager): int
    {
        if (! $tenantManager->isMultiTenant()) {
            $this->error('Nur im Modus TENANCY_MODE=multi verfügbar.');

            return self::FAILURE;
        }

        $email = Str::lower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->error('Bitte eine gültige E-Mail-Adresse angeben.');

            return self::FAILURE;
        }

        // Passwort nur interaktiv — landet so nicht in der Shell-History.
        $password = (string) $this->secret('Passwort (mind. 12 Zeichen)');

        if (mb_strlen($password) < 12) {
            $this->error('Das Passwort muss mindestens 12 Zeichen lang sein.');

            return self::FAILURE;
        }

        if ($password !== (string) $this->secret('Passwort wiederholen')) {
            $this->error('Die Passwörter stimmen nicht überein.');

            return self::FAILURE;
        }

        $admin = PlatformAdmin::query()->firstOrNew(['email' => $email]);
        $isNew = ! $admin->exists;

        $admin->fill([
            'name' => $this->option('name') ?: ($admin->name ?: Str::before($email, '@')),
            'password' => $password,
        ])->save();

        $this->info($isNew
            ? "Plattform-Admin {$email} angelegt. Login: ".config('tenancy.scheme').'://'.config('tenancy.platform_host').'/admin'
            : "Passwort für {$email} neu gesetzt.");

        return self::SUCCESS;
    }
}
