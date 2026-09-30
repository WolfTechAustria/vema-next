<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Vereinsbezogene Jobs laufen über "tenants:run": im Modus "single" genau
 * einmal wie bisher, im Modus "multi" für jeden freigeschalteten Verein.
 */

// täglich morgens: rund/halbrund-Reminder
Schedule::command('tenants:run', ['birthdays:send-reminders'])
    ->dailyAt('07:00')
    ->withoutOverlapping();

// am 1. jedes Monats: PDF-Geburtstagsliste an den Vorstand
Schedule::command('tenants:run', ['birthdays:send-monthly-list'])
    ->monthlyOn(1, '06:30')
    ->withoutOverlapping();

Schedule::command('tenants:run', ['duty:send-reminders'])
    ->dailyAt('07:00')
    ->withoutOverlapping();

// Testmodus: Test-DB nachts auf Live-Stand (nur wenn in den Einstellungen gewählt)
Schedule::command('tenants:run', ['demo:reset --if-nightly'])
    ->dailyAt(config('demo.reset_time'))
    ->withoutOverlapping();

// Plattform: abgelaufene, nie bestätigte Registrierungen entfernen, Erinnerungen
if (config('tenancy.mode') === 'multi') {
    Schedule::command('tenants:prune-unverified')
        ->dailyAt('04:00')
        ->withoutOverlapping();

    // Erinnerungen an Testende und Lizenzablauf
    Schedule::command('tenants:send-reminders')
        ->dailyAt('08:00')
        ->withoutOverlapping();
}
