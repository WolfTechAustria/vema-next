<?php

namespace App\Providers;

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
