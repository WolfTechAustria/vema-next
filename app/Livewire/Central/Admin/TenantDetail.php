<?php

namespace App\Livewire\Central\Admin;

use App\Enums\Plan;
use App\Enums\TenantStatus;
use App\Mail\Central\TenantWelcomeMail;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use App\Services\TenantProvisioner;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Throwable;

/**
 * Ein Verein im Plattform-Admin: Paket, Lizenz, Testphase, Sperre.
 */
class TenantDetail extends Component
{
    public Tenant $tenant;

    public string $plan = '';

    public string $license_valid_until = '';

    public string $trial_ends_at = '';

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->fillForm();
    }

    private function fillForm(): void
    {
        $this->plan = $this->tenant->plan->value;
        $this->license_valid_until = $this->tenant->license_valid_until?->format('Y-m-d') ?? '';
        $this->trial_ends_at = $this->tenant->trial_ends_at?->format('Y-m-d') ?? '';
    }

    public function save(): void
    {
        $validated = $this->validate([
            'plan' => ['required', Rule::enum(Plan::class)],
            'license_valid_until' => ['nullable', 'date'],
            'trial_ends_at' => ['nullable', 'date'],
        ], [
            'license_valid_until.date' => 'Bitte ein gültiges Datum eingeben.',
            'trial_ends_at.date' => 'Bitte ein gültiges Datum eingeben.',
        ]);

        $trialEndsAt = $validated['trial_ends_at']
            ? now()->parse($validated['trial_ends_at'])->endOfDay()
            : null;

        $licenseValidUntil = $validated['license_valid_until'] ?: null;

        $attributes = [
            'plan' => $validated['plan'],
            'license_valid_until' => $licenseValidUntil,
            'trial_ends_at' => $trialEndsAt,
        ];

        // Geänderte Fristen: Erinnerungen für die neue Frist wieder zulassen.
        if ($this->tenant->trial_ends_at?->format('Y-m-d') !== $trialEndsAt?->format('Y-m-d')) {
            $attributes['trial_reminder'] = null;
        }

        if ($this->tenant->license_valid_until?->format('Y-m-d') !== $licenseValidUntil) {
            $attributes['license_reminder'] = null;
        }

        $this->tenant->update($attributes);
        $this->audit('Paket/Lizenz geändert', $attributes);

        $this->tenant->refresh();
        $this->fillForm();

        session()->flash('success', 'Gespeichert.');
    }

    public function extendTrial(int $days): void
    {
        $days = max(1, min($days, 90));
        $base = $this->tenant->isOnTrial() ? $this->tenant->trial_ends_at : now();

        $this->tenant->update([
            'trial_ends_at' => $base->copy()->addDays($days)->endOfDay(),
            'trial_reminder' => null,
        ]);

        $this->audit("Testphase um {$days} Tage verlängert");

        $this->tenant->refresh();
        $this->fillForm();

        session()->flash('success', "Testphase bis {$this->tenant->trial_ends_at->format('d.m.Y')} verlängert.");
    }

    public function suspend(): void
    {
        if ($this->tenant->status !== TenantStatus::Active) {
            return;
        }

        $this->tenant->update(['status' => TenantStatus::Suspended]);
        $this->audit('Gesperrt');

        session()->flash('success', 'Der Verein ist gesperrt. Anmeldungen sind nicht mehr möglich.');
    }

    public function reactivate(): void
    {
        // Nur eingerichtete Vereine — sonst gibt es keine Datenbank.
        if ($this->tenant->provisioned_at === null || $this->tenant->status === TenantStatus::Active) {
            return;
        }

        $this->tenant->update(['status' => TenantStatus::Active]);
        $this->audit('Entsperrt');

        session()->flash('success', 'Der Verein ist wieder freigeschaltet.');
    }

    /**
     * Bestätigt, aber Einrichtung fehlgeschlagen: erneut versuchen.
     */
    public function provision(TenantProvisioner $provisioner): void
    {
        if ($this->tenant->email_verified_at === null || $this->tenant->provisioned_at !== null) {
            return;
        }

        try {
            $passwordSetupUrl = $provisioner->provision($this->tenant);
        } catch (Throwable $exception) {
            report($exception);
            session()->flash('error', 'Einrichtung fehlgeschlagen: '.$exception->getMessage());

            return;
        }

        $this->tenant->refresh();
        Mail::to($this->tenant->contact_email)->send(new TenantWelcomeMail($this->tenant, $passwordSetupUrl));
        $this->audit('Einrichtung nachgeholt');

        session()->flash('success', 'Verein eingerichtet, Willkommensmail verschickt.');
    }

    /**
     * Nutzung aus der Vereinsdatenbank; null, wenn (noch) nicht erreichbar.
     *
     * @return array{members: int, staff: int}|null
     */
    private function usage(TenantManager $tenantManager): ?array
    {
        if ($this->tenant->provisioned_at === null) {
            return null;
        }

        try {
            return $tenantManager->runFor($this->tenant, fn (): array => [
                'members' => Member::query()->active()->count(),
                'staff' => User::query()->where('enabled', true)->count(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function audit(string $action, array $details = []): void
    {
        Log::info('[Plattform-Admin] '.$action, [
            'admin' => auth('platform')->user()?->email,
            'tenant' => $this->tenant->slug,
            'details' => $details,
        ]);
    }

    public function render(TenantManager $tenantManager)
    {
        return view('livewire.central.admin.tenant-detail', [
            'usage' => $this->usage($tenantManager),
            'plans' => Plan::cases(),
        ])->layout('layouts.platform-admin', [
            'title' => $this->tenant->name.' | Plattform-Admin',
        ]);
    }
}
