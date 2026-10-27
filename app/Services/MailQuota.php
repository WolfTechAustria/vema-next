<?php

namespace App\Services;

use App\Enums\Limit;
use App\Exceptions\MailQuotaExceededException;
use App\Mail\Contracts\ExemptFromMailQuota;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Monatliches E-Mail-Kontingent je Verein (nur Plattform-Betrieb).
 *
 * Gezählt werden Mails an Mitglieder und Kontakte (Rundschreiben,
 * Vorschreibungen, Rechnungen, Erinnerungen …) je Empfänger. Anmelde-Links,
 * Passwort-Mails und Hinweise an den Vorstand zählen nicht (ExemptFromMailQuota),
 * ebenso wenig Mails aus dem Testmodus (gehen nur an den Benutzer selbst).
 */
class MailQuota
{
    public function __construct(private TenantManager $tenantManager) {}

    /**
     * Verein, dessen Kontingent für den aktuellen Versand gilt; null = keins.
     */
    public function tenant(): ?Tenant
    {
        if (app(DemoMode::class)->isActive()) {
            return null;
        }

        return $this->tenantManager->current();
    }

    public function limit(): ?int
    {
        return $this->tenant()?->limit(Limit::EmailsPerMonth);
    }

    public function sentThisMonth(Tenant $tenant): int
    {
        return (int) DB::connection($this->tenantManager->landlordConnection())
            ->table('tenant_mail_usage')
            ->where('tenant_id', $tenant->getKey())
            ->where('month', now()->format('Y-m'))
            ->value('sent');
    }

    /**
     * Noch verfügbare Mails in diesem Monat; null = unbegrenzt.
     */
    public function remaining(): ?int
    {
        $tenant = $this->tenant();
        $limit = $tenant?->limit(Limit::EmailsPerMonth);

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->sentThisMonth($tenant));
    }

    /**
     * @throws MailQuotaExceededException
     */
    public function ensureAvailable(int $count): void
    {
        $remaining = $this->remaining();

        if ($remaining !== null && $count > $remaining) {
            throw new MailQuotaExceededException($this->limit(), $remaining, $count);
        }
    }

    public function record(int $count): void
    {
        $tenant = $this->tenant();

        if ($tenant === null || $count < 1) {
            return;
        }

        DB::connection($this->tenantManager->landlordConnection())
            ->table('tenant_mail_usage')
            ->upsert(
                [['tenant_id' => $tenant->getKey(), 'month' => now()->format('Y-m'), 'sent' => $count]],
                ['tenant_id', 'month'],
                ['sent' => DB::raw('sent + '.$count)],
            );
    }

    public static function counts(?string $mailableClass): bool
    {
        return $mailableClass === null || ! is_subclass_of($mailableClass, ExemptFromMailQuota::class);
    }
}
