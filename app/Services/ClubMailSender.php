<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Database\QueryException;

/**
 * Setzt den Absender aus den Vereinseinstellungen (sonst .env). Merkt sich
 * den Absender aus der Konfiguration, damit beim Wechsel zu einem Verein
 * ohne eigenen Absender wieder dieser gilt.
 */
class ClubMailSender
{
    /**
     * @var array{address: ?string, name: ?string}|null
     */
    private ?array $configuredFrom = null;

    public function apply(): void
    {
        $this->rememberConfiguredFrom();

        // Plattform-Domain ohne Verein: keine Vereinseinstellungen vorhanden.
        $tenantManager = app(TenantManager::class);

        if ($tenantManager->isMultiTenant() && $tenantManager->current() === null) {
            $this->reset();

            return;
        }

        try {
            $setting = Setting::current();
        } catch (QueryException) {
            // Vereinsdatenbank wird gerade erst eingerichtet (noch ohne Tabellen).
            $this->reset();

            return;
        }

        config(['mail.from' => filled($setting->mail_from_address)
            ? [
                'address' => $setting->mail_from_address,
                'name' => $setting->mail_from_name ?: $setting->name,
            ]
            : $this->configuredFrom,
        ]);
    }

    /**
     * Absender aus der Konfiguration wiederherstellen (kein Verein aktiv).
     */
    public function reset(): void
    {
        $this->rememberConfiguredFrom();

        config(['mail.from' => $this->configuredFrom]);
    }

    private function rememberConfiguredFrom(): void
    {
        $this->configuredFrom ??= [
            'address' => config('mail.from.address'),
            'name' => config('mail.from.name'),
        ];
    }
}
