<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Mail\Central\TenantReminderMail;
use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TenantsSendReminders extends Command
{
    protected $signature = 'tenants:send-reminders';

    protected $description = 'Erinnert Vereine an das Ende der Testphase und den Ablauf der Lizenz (TENANCY_MODE=multi)';

    public function handle(TenantManager $tenantManager): int
    {
        if (! $tenantManager->isMultiTenant()) {
            $this->error('Nur im Modus TENANCY_MODE=multi verfügbar.');

            return self::FAILURE;
        }

        $sent = 0;

        $tenants = Tenant::query()
            ->where('status', TenantStatus::Active)
            ->whereNotNull('provisioned_at')
            ->get();

        foreach ($tenants as $tenant) {
            foreach ([
                'trial_reminder' => $this->trialReminderFor($tenant),
                'license_reminder' => $this->licenseReminderFor($tenant),
            ] as $column => $reminder) {
                if ($reminder === null) {
                    continue;
                }

                try {
                    Mail::to($tenant->contact_email)->send(new TenantReminderMail($tenant, $reminder));
                } catch (Throwable $exception) {
                    report($exception);
                    $this->components->error("{$tenant->slug}: {$exception->getMessage()}");

                    continue;
                }

                $tenant->forceFill([$column => $reminder])->save();
                $this->line("{$tenant->slug}: {$reminder}");
                $sent++;
            }
        }

        $this->info("{$sent} Erinnerung(en) verschickt.");

        return self::SUCCESS;
    }

    /**
     * Fällige Erinnerung zur Testphase — jede nur einmal pro Frist.
     */
    private function trialReminderFor(Tenant $tenant): ?string
    {
        if ($tenant->trial_ends_at === null) {
            return null;
        }

        $daysLeft = $tenant->trialDaysLeft();

        $due = match (true) {
            $daysLeft !== null && $daysLeft <= 1 => 'trial-1',
            $daysLeft !== null && $daysLeft <= 7 => 'trial-7',
            $daysLeft === null && $tenant->trial_ends_at->isAfter(now()->subDays(7)) => 'trial-ended',
            default => null,
        };

        return $this->unlessAlreadySent($due, $tenant->trial_reminder, ['trial-7', 'trial-1', 'trial-ended']);
    }

    /**
     * Fällige Erinnerung zum Lizenzablauf — jede nur einmal pro Frist.
     */
    private function licenseReminderFor(Tenant $tenant): ?string
    {
        if ($tenant->license_valid_until === null || $tenant->isOnTrial()) {
            return null;
        }

        $daysLeft = (int) now()->startOfDay()->diffInDays($tenant->license_valid_until->copy()->startOfDay(), false);

        $due = match (true) {
            $daysLeft < 0 => null,
            $daysLeft <= 7 => 'license-7',
            $daysLeft <= 30 => 'license-30',
            default => null,
        };

        return $this->unlessAlreadySent($due, $tenant->license_reminder, ['license-30', 'license-7']);
    }

    /**
     * Nicht erneut schicken, wenn diese oder eine spätere Stufe schon raus ist.
     *
     * @param  array<int, string>  $stages  in zeitlicher Reihenfolge
     */
    private function unlessAlreadySent(?string $due, ?string $lastSent, array $stages): ?string
    {
        if ($due === null) {
            return null;
        }

        $lastIndex = $lastSent === null ? -1 : (int) array_search($lastSent, $stages, true);

        return array_search($due, $stages, true) > $lastIndex ? $due : null;
    }
}
