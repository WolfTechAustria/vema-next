<?php

namespace App\Services;

use App\Enums\Plan;
use App\Enums\TenantStatus;
use App\Mail\Central\PlatformNotificationMail;
use App\Mail\Central\TenantVerificationMail;
use App\Mail\Central\TenantWelcomeMail;
use App\Models\Tenant;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Selbstregistrierung auf der Plattform: Verein anlegen (wartet auf
 * E-Mail-Bestätigung), nach der Bestätigung automatisch einrichten und die
 * Testphase starten.
 */
class TenantRegistration
{
    public function __construct(private TenantProvisioner $provisioner) {}

    /**
     * Regeln für die Vereinsadresse (Subdomain).
     *
     * @return array<int, mixed>
     */
    public static function slugRules(): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:40',
            'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
            'not_regex:/--/',
            Rule::notIn(config('tenancy.reserved_slugs')),
            Rule::unique(Tenant::class, 'slug'),
        ];
    }

    /**
     * Vorschlag für die Vereinsadresse aus dem Vereinsnamen.
     */
    public static function suggestSlug(string $clubName): string
    {
        $slug = Str::slug(str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], mb_strtolower($clubName)));

        return trim(Str::limit($slug, 40, ''), '-');
    }

    /**
     * @param  array{club_name: string, slug: string, contact_name: string, contact_email: string}  $data
     */
    public function register(array $data, ?Plan $requestedPlan = null): Tenant
    {
        $token = Str::random(48);

        $tenant = Tenant::create([
            'slug' => $data['slug'],
            'name' => $data['club_name'],
            'database' => $this->provisioner->databaseNameFor($data['slug']),
            'status' => TenantStatus::Pending,
            'plan' => Plan::Starter,
            'requested_plan' => $requestedPlan,
            'contact_name' => $data['contact_name'],
            'contact_email' => Str::lower($data['contact_email']),
            'verification_token' => hash('sha256', $token),
        ]);

        Mail::to($tenant->contact_email)->send(new TenantVerificationMail(
            $tenant,
            route('central.register.verify', ['token' => $token]),
        ));

        $this->notifyPlatform($tenant, 'Neue Registrierung');

        return $tenant;
    }

    /**
     * Noch nicht bestätigte Registrierung zum Link, sofern nicht abgelaufen.
     */
    public function findPendingByToken(string $token): ?Tenant
    {
        return Tenant::query()
            ->where('verification_token', hash('sha256', $token))
            ->whereNull('email_verified_at')
            ->where('created_at', '>=', now()->subHours((int) config('tenancy.verification_hours')))
            ->first();
    }

    /**
     * Bestätigt die E-Mail-Adresse und richtet den Verein ein.
     *
     * @return string Link „Passwort festlegen“ auf der Subdomain des Vereins
     */
    public function verifyAndProvision(Tenant $tenant): string
    {
        $tenant->forceFill([
            'email_verified_at' => now(),
            'verification_token' => null,
        ])->save();

        try {
            $passwordSetupUrl = $this->provisioner->provision($tenant);
        } catch (Throwable $exception) {
            report($exception);
            $this->notifyPlatform($tenant, 'Einrichtung fehlgeschlagen', $exception->getMessage());

            throw $exception;
        }

        Mail::to($tenant->contact_email)->send(new TenantWelcomeMail($tenant->refresh(), $passwordSetupUrl));

        $this->notifyPlatform($tenant, 'Verein eingerichtet, Testphase gestartet');

        return $passwordSetupUrl;
    }

    private function notifyPlatform(Tenant $tenant, string $event, ?string $details = null): void
    {
        $recipient = config('tenancy.platform_admin_email');

        if (blank($recipient)) {
            return;
        }

        try {
            Mail::to($recipient)->send(new PlatformNotificationMail($tenant, $event, $details));
        } catch (Throwable $exception) {
            // Die Registrierung selbst darf daran nicht scheitern.
            report($exception);
        }
    }
}
