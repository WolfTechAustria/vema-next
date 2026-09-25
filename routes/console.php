<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// täglich morgens: rund/halbrund-Reminder
Schedule::command('birthdays:send-reminders')
    ->dailyAt('07:00')
    ->withoutOverlapping();

// am 1. jedes Monats: PDF-Geburtstagsliste an den Vorstand
Schedule::command('birthdays:send-monthly-list')
    ->monthlyOn(1, '06:30')
    ->withoutOverlapping();

Schedule::command('duty:send-reminders')
    ->dailyAt('07:00')
    ->withoutOverlapping();

// Testmodus: Test-DB nachts auf Live-Stand (nur wenn in den Einstellungen gewählt)
Schedule::command('demo:reset --if-nightly')
    ->dailyAt(config('demo.reset_time'))
    ->withoutOverlapping();
