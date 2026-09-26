<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\DemoMode;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(DemoMode::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureDemoMode();
        $this->configureClubMailSender();
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
            $setting = Setting::current();

            if (filled($setting->mail_from_address)) {
                config([
                    'mail.from.address' => $setting->mail_from_address,
                    'mail.from.name' => $setting->mail_from_name ?: $setting->name,
                ]);
            }
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
