<?php

namespace App\Livewire\Central\Admin;

use App\Enums\Feature;
use App\Enums\Limit;
use App\Enums\Plan;
use App\Enums\TenantStatus;
use App\Mail\Central\TenantAccessMail;
use App\Mail\Central\TenantWelcomeMail;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Services\HomepageHosts;
use App\Services\MailQuota;
use App\Services\StorageUsage;
use App\Services\TenantDeleter;
use App\Services\TenantManager;
use App\Services\TenantProvisioner;
use App\Services\TenantRegistration;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Throwable;

/**
 * Ein Verein im Plattform-Admin: Paket, Lizenz, Testphase, Stammdaten,
 * Zugang, Domains, Sperre und Löschen.
 */
class TenantDetail extends Component
{
    public Tenant $tenant;

    public string $plan = '';

    public string $license_valid_until = '';

    public string $trial_ends_at = '';

    public string $name = '';

    public string $contact_name = '';

    public string $contact_email = '';

    public string $admin_notes = '';

    public string $newDomain = '';

    public string $newDomainPurpose = 'app';

    public string $deleteConfirmation = '';

    /**
     * @var array<string, string> Funktion => 'plan' | 'on' | 'off'
     */
    public array $featureOverrides = [];

    /**
     * @var array<string, string> Limit => 'plan' | 'custom' | 'unlimited'
     */
    public array $limitModes = [];

    /**
     * @var array<string, string> Limit => eigener Wert
     */
    public array $limitValues = [];

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
        $this->name = $this->tenant->name;
        $this->contact_name = (string) $this->tenant->contact_name;
        $this->contact_email = (string) $this->tenant->contact_email;
        $this->admin_notes = (string) $this->tenant->admin_notes;

        foreach (Feature::cases() as $feature) {
            $this->featureOverrides[$feature->value] = match ($this->tenant->featureOverride($feature)) {
                true => 'on',
                false => 'off',
                null => 'plan',
            };
        }

        foreach (Limit::cases() as $limit) {
            $value = $this->tenant->limit_overrides[$limit->value] ?? null;

            $this->limitModes[$limit->value] = match (true) {
                ! $this->tenant->hasLimitOverride($limit) => 'plan',
                $value === null => 'unlimited',
                default => 'custom',
            };

            $this->limitValues[$limit->value] = (string) ($value ?? '');
        }
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

        $changes = $this->changesOf([
            'plan' => $validated['plan'],
            'license_valid_until' => $licenseValidUntil,
            'trial_ends_at' => $trialEndsAt?->format('Y-m-d'),
        ]);

        $this->tenant->update($attributes);

        if ($changes !== []) {
            $this->audit('Paket/Lizenz geändert', $changes);
        }

        $this->refreshTenant();

        session()->flash('success', 'Gespeichert.');
    }

    public function saveDetails(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'contact_email' => ['required', 'email', 'max:150'],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'name.required' => 'Bitte einen Vereinsnamen eingeben.',
            'contact_email.required' => 'Bitte eine Kontakt-E-Mail eingeben.',
            'contact_email.email' => 'Bitte eine gültige E-Mail-Adresse eingeben.',
        ]);

        $validated['contact_email'] = Str::lower($validated['contact_email']);
        $validated['contact_name'] = $validated['contact_name'] ?: null;
        $validated['admin_notes'] = $validated['admin_notes'] ?: null;

        // Notizen nicht in den Verlauf schreiben — nur dass sie geändert wurden.
        $changes = $this->changesOf(collect($validated)->except('admin_notes')->all());

        if ($validated['admin_notes'] !== $this->tenant->admin_notes) {
            $changes['admin_notes'] = 'geändert';
        }

        $this->tenant->update($validated);

        if ($changes !== []) {
            $this->audit('Stammdaten geändert', $changes);
        }

        $this->refreshTenant();

        session()->flash('success', 'Stammdaten gespeichert.');
    }

    /**
     * Individuelle Anpassungen: einzelne Funktionen frei- oder abschalten,
     * Limits abweichend vom Paket (z. B. zugebuchter Speicher).
     */
    public function saveOverrides(): void
    {
        $rules = [];

        foreach (Feature::cases() as $feature) {
            $rules["featureOverrides.{$feature->value}"] = ['required', Rule::in(['plan', 'on', 'off'])];
        }

        foreach (Limit::cases() as $limit) {
            $rules["limitModes.{$limit->value}"] = ['required', Rule::in(['plan', 'custom', 'unlimited'])];
            $rules["limitValues.{$limit->value}"] = [
                Rule::requiredIf(($this->limitModes[$limit->value] ?? null) === 'custom'),
                'nullable',
                'integer',
                'min:1',
                'max:100000000',
            ];
        }

        $this->validate($rules, [
            'limitValues.*.required' => 'Bitte einen Wert eingeben.',
            'limitValues.*.integer' => 'Bitte eine ganze Zahl eingeben.',
            'limitValues.*.min' => 'Mindestens 1.',
        ]);

        $featureOverrides = [];

        foreach (Feature::cases() as $feature) {
            $mode = $this->featureOverrides[$feature->value];

            if ($mode !== 'plan') {
                $featureOverrides[$feature->value] = $mode === 'on';
            }
        }

        $limitOverrides = [];

        foreach (Limit::cases() as $limit) {
            $limitOverrides += match ($this->limitModes[$limit->value]) {
                'custom' => [$limit->value => (int) $this->limitValues[$limit->value]],
                'unlimited' => [$limit->value => null],
                default => [],
            };
        }

        $before = $this->overridesSummary($this->tenant);

        $this->tenant->update([
            'feature_overrides' => $featureOverrides ?: null,
            'limit_overrides' => $limitOverrides ?: null,
        ]);

        $after = $this->overridesSummary($this->tenant);

        if ($before !== $after) {
            $this->audit('Individuelle Anpassungen geändert', ['vorher' => $before ?: 'keine', 'nachher' => $after ?: 'keine']);
        }

        $this->refreshTenant();

        session()->flash('success', 'Individuelle Anpassungen gespeichert.');
    }

    /**
     * Lesbare Zusammenfassung für den Verlauf, z. B. "Dienstplan: an, Speicher (MB): 10 GB".
     */
    private function overridesSummary(Tenant $tenant): string
    {
        $parts = [];

        foreach (Feature::cases() as $feature) {
            $override = $tenant->featureOverride($feature);

            if ($override !== null) {
                $parts[] = $feature->label().': '.($override ? 'an' : 'aus');
            }
        }

        foreach (Limit::cases() as $limit) {
            if ($tenant->hasLimitOverride($limit)) {
                $parts[] = $limit->label().': '.$limit->format($tenant->limit($limit));
            }
        }

        return implode(', ', $parts);
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

        $this->refreshTenant();

        session()->flash('success', "Testphase bis {$this->tenant->trial_ends_at->format('d.m.Y')} verlängert.");
    }

    public function endTrial(): void
    {
        if (! $this->tenant->isOnTrial()) {
            return;
        }

        $this->tenant->update(['trial_ends_at' => now()]);
        $this->audit('Testphase beendet');

        $this->refreshTenant();

        session()->flash('success', "Testphase beendet — es gilt jetzt {$this->tenant->plan->label()}.");
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
     * Registrierung ohne Klick auf den Bestätigungslink einrichten (z. B. Mail
     * im Spam, Kontakt telefonisch bestätigt).
     */
    public function verifyManually(TenantRegistration $registration): void
    {
        if ($this->tenant->email_verified_at !== null) {
            return;
        }

        try {
            $registration->verifyAndProvision($this->tenant);
        } catch (Throwable $exception) {
            session()->flash('error', 'Einrichtung fehlgeschlagen: '.$exception->getMessage());
            $this->refreshTenant();

            return;
        }

        $this->audit('Manuell bestätigt und eingerichtet');
        $this->refreshTenant();

        session()->flash('success', 'Verein eingerichtet, Willkommensmail verschickt.');
    }

    public function resendVerification(TenantRegistration $registration): void
    {
        if ($this->tenant->email_verified_at !== null) {
            return;
        }

        $registration->resendVerification($this->tenant);
        $this->audit('Bestätigungslink erneut gesendet', ['to' => $this->tenant->contact_email]);
        $this->refreshTenant();

        session()->flash('success', "Neuer Bestätigungslink an {$this->tenant->contact_email} gesendet.");
    }

    /**
     * Neuer Link „Passwort festlegen“ für den Vereins-Admin mit der
     * Kontakt-E-Mail (sonst den ersten Admin des Vereins).
     */
    public function sendAccessLink(TenantManager $tenantManager): void
    {
        if ($this->tenant->provisioned_at === null) {
            return;
        }

        $access = $tenantManager->runFor($this->tenant, function (Tenant $tenant): ?array {
            $user = User::clubAdmin($tenant->contact_email);

            if ($user === null) {
                return null;
            }

            return [
                'email' => $user->email,
                'username' => $user->username,
                'url' => route('password.reset', [
                    'token' => Password::broker()->createToken($user),
                    'email' => $user->email,
                ]),
            ];
        });

        if ($access === null) {
            session()->flash('error', 'Im Verein gibt es keinen Admin-Zugang.');

            return;
        }

        Mail::to($access['email'])->send(new TenantAccessMail($this->tenant, $access['username'], $access['url']));
        $this->audit('Zugangslink gesendet', ['to' => $access['email']]);

        session()->flash('success', "Zugangslink an {$access['email']} gesendet.");
    }

    public function addDomain(): void
    {
        $this->newDomain = Str::lower(trim($this->newDomain));

        $validated = $this->validate([
            'newDomain' => [
                'required',
                'max:190',
                'regex:/^(?=.{4,190}$)([a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/',
                'not_regex:/(^|\.)'.preg_quote(config('tenancy.tenant_domain'), '/').'$/',
                Rule::unique(TenantDomain::class, 'domain'),
            ],
            'newDomainPurpose' => ['required', Rule::in(['app', HomepageHosts::PURPOSE])],
        ], [
            'newDomain.required' => 'Bitte eine Domain eingeben.',
            'newDomain.regex' => 'Bitte eine gültige Domain eingeben, z. B. www.mein-verein.at.',
            'newDomain.not_regex' => 'Adressen unter '.config('tenancy.tenant_domain').' sind nicht möglich.',
            'newDomain.unique' => 'Diese Domain ist bereits einem Verein zugeordnet.',
        ]);

        $this->tenant->domains()->create([
            'domain' => $validated['newDomain'],
            'purpose' => $validated['newDomainPurpose'],
        ]);

        Cache::forget('homepage.host.'.$validated['newDomain']);
        $this->audit('Domain hinzugefügt', ['domain' => $validated['newDomain'], 'purpose' => $validated['newDomainPurpose']]);

        $this->reset('newDomain', 'newDomainPurpose');

        session()->flash('success', 'Domain hinzugefügt. DNS und Zertifikat müssen auf dem Server eingerichtet sein.');
    }

    public function removeDomain(int $domainId): void
    {
        $domain = $this->tenant->domains()->find($domainId);

        if ($domain === null) {
            return;
        }

        $domain->delete();
        Cache::forget('homepage.host.'.$domain->domain);
        $this->audit('Domain entfernt', ['domain' => $domain->domain]);

        session()->flash('success', "{$domain->domain} entfernt.");
    }

    public function deleteTenant(TenantDeleter $deleter)
    {
        if ($this->deleteConfirmation !== $this->tenant->slug) {
            $this->addError('deleteConfirmation', 'Zur Bestätigung bitte genau „'.$this->tenant->slug.'“ eingeben.');

            return null;
        }

        try {
            $deleter->delete($this->tenant);
        } catch (Throwable $exception) {
            report($exception);
            session()->flash('error', 'Löschen fehlgeschlagen: '.$exception->getMessage());

            return null;
        }

        // Verlauf wird mitgelöscht — der Eintrag im Log bleibt.
        Log::warning('[Plattform-Admin] Verein gelöscht', [
            'admin' => auth('platform')->user()?->email,
            'tenant' => $this->tenant->slug,
            'name' => $this->tenant->name,
            'database' => $this->tenant->database,
        ]);

        session()->flash('success', "{$this->tenant->name} wurde endgültig gelöscht.");

        return $this->redirectRoute('central.admin.tenants', navigate: true);
    }

    private function refreshTenant(): void
    {
        $this->tenant->refresh();
        $this->fillForm();
    }

    /**
     * Geänderte Werte als "alt → neu" für den Verlauf.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function changesOf(array $values): array
    {
        $changes = [];

        foreach ($values as $key => $new) {
            $old = $this->tenant->getAttribute($key);
            $old = match (true) {
                $old instanceof BackedEnum => $old->value,
                $old instanceof DateTimeInterface => $old->format('Y-m-d'),
                default => $old,
            };

            if ((string) $old !== (string) $new) {
                $changes[$key] = ($old ?? '–').' → '.($new ?? '–');
            }
        }

        return $changes;
    }

    /**
     * Nutzung des Vereins; null, wenn die Datenbank (noch) nicht erreichbar ist.
     *
     * @return array{members: int, staff: int, emails: int, storage_mb: float}|null
     */
    private function usage(TenantManager $tenantManager, MailQuota $mailQuota, StorageUsage $storageUsage): ?array
    {
        if ($this->tenant->provisioned_at === null) {
            return null;
        }

        try {
            $counts = $tenantManager->runFor($this->tenant, fn (): array => [
                'members' => Member::query()->active()->count(),
                'staff' => User::query()->where('enabled', true)->count(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        return $counts + [
            'emails' => $mailQuota->sentThisMonth($this->tenant),
            'storage_mb' => round($storageUsage->usedBytes($this->tenant) / 1024 / 1024, 1),
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function audit(string $action, array $details = []): void
    {
        $this->tenant->recordEvent($action, $details);
    }

    public function render(TenantManager $tenantManager, MailQuota $mailQuota, StorageUsage $storageUsage)
    {
        $usage = $this->usage($tenantManager, $mailQuota, $storageUsage);
        $tenant = $this->tenant;

        // Nach der Testphase: gebuchtes Paket, individuelle Werte bleiben.
        $limitAfterTrial = fn (Limit $limit): ?int => $tenant->hasLimitOverride($limit)
            ? $tenant->limit($limit)
            : $tenant->plan->limit($limit);

        return view('livewire.central.admin.tenant-detail', [
            'usage' => $usage,
            'plans' => Plan::cases(),
            'effectivePlan' => $tenant->effectivePlan(),
            'featureCases' => Feature::cases(),
            'limitCases' => Limit::cases(),
            'limitWarnings' => $usage === null ? [] : array_filter([
                $this->limitWarning(fn (Limit $limit) => $tenant->limit($limit), $usage, 'aktuell'),
                $tenant->isOnTrial() ? $this->limitWarning($limitAfterTrial, $usage, 'nach der Testphase') : null,
            ]),
            'domains' => $tenant->domains()->orderBy('domain')->get(),
            'events' => $tenant->events()->with('admin')->latest('id')->limit(30)->get(),
            'verificationExpired' => $tenant->email_verified_at === null
                && $tenant->created_at->lt(now()->subHours((int) config('tenancy.verification_hours'))),
        ])->layout('layouts.platform-admin', [
            'title' => $tenant->name.' | Plattform-Admin',
        ]);
    }

    /**
     * @param  callable(Limit): ?int  $limitFor
     * @param  array{members: int, staff: int, emails: int, storage_mb: float}  $usage
     */
    private function limitWarning(callable $limitFor, array $usage, string $when): ?string
    {
        $exceeded = [];

        foreach (['members' => Limit::Members, 'staff' => Limit::Staff] as $key => $limit) {
            $value = $limitFor($limit);

            if ($value !== null && $usage[$key] > $value) {
                $exceeded[] = $usage[$key].' von '.$limit->format($value).' '.($key === 'members' ? 'Mitgliedern' : 'Vorstandszugängen');
            }
        }

        if ($exceeded === []) {
            return null;
        }

        return "Limit {$when} überschritten: ".implode(', ', $exceeded).'.';
    }
}
