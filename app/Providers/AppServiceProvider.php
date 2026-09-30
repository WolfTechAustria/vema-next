<?php

namespace App\Providers;

use App\Enums\Feature;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Services\ClubMailSender;
use App\Services\DemoMode;
use App\Services\PlanEntitlements;
use App\Services\TenantManager;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(DemoMode::class);
        $this->app->scoped(TenantManager::class);
        $this->app->scoped(ClubMailSender::class);
        $this->app->scoped(PlanEntitlements::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureTenancy();
        $this->configureDemoMode();
        $this->configureClubMailSender();
        $this->configurePlanFeatures();
    }

    /**
     * @feature('duty_plan') … @endfeature — blendet Inhalte aus, deren Funktion
     * im Paket des Vereins fehlt (im Modus "single" immer sichtbar).
     */
    protected function configurePlanFeatures(): void
    {
        Blade::if('feature', fn (string $feature): bool => app(PlanEntitlements::class)->allows(Feature::from($feature)));

        // Auch Livewire-Aktionen auf einer bereits geöffneten Seite prüfen.
        Livewire::addPersistentMiddleware([EnsureFeatureEnabled::class]);
    }

    /**
     * Plattform-Betrieb (TENANCY_MODE=multi): bis ein Verein erkannt ist,
     * arbeitet alles gegen die zentrale Datenbank (siehe IdentifyTenant).
     */
    protected function configureTenancy(): void
    {
        $tenantManager = app(TenantManager::class);

        if ($tenantManager->isMultiTenant()) {
            $tenantManager->bootLandlord();
        }
    }

    /**
     * Absender aus den Vereinseinstellungen statt aus .env, sofern hinterlegt.
     * Wird erst gesetzt, wenn der MailManager tatsächlich gebraucht wird —
     * so kostet nicht jeder Request eine Abfrage, und die Mailer übernehmen
     * die Adresse beim Erzeugen (auch für die IMAP-Kopie).
     */
    protected function configureClubMailSender(): void
    {
        $this->app->afterResolving('mail.manager', function (): void {
            app(ClubMailSender::class)->apply();
        });
    }

    /**
     * Mails aus dem Testmodus bekommen ein "[TEST]" in den Betreff.
     */
    protected function configureDemoMode(): void
    {
        Event::listen(function (MessageSending $event): void {
            if (app(DemoMode::class)->isActive()) {
                $event->message->subject('[TEST] '.$event->message->getSubject());
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
