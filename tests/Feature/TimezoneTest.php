<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

/*
 * VEMA speichert Zeiten als Wanduhrzeit des Vereins. Läuft die App in UTC,
 * starten geplante Aufgaben und Protokollzeiten 1–2 Stunden verschoben.
 */

it('runs in the club timezone by default', function () {
    expect(config('app.timezone'))->toBe('Europe/Vienna')
        ->and(now()->getTimezone()->getName())->toBe('Europe/Vienna');
});

it('schedules the daily reminders at 07:00 club time', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'duty:send-reminders'));

    expect($event->expression)->toBe('0 7 * * *')
        ->and($event->timezone)->toBe('Europe/Vienna');
});

it('converts existing activity log timestamps from utc to club time', function () {
    DB::table('tb_activity_log')->insert([
        ['action' => 'user.login', 'description' => 'Anmeldung', 'created_at' => '2026-07-01 08:00:00', 'updated_at' => '2026-07-01 08:00:00'],
        ['action' => 'user.login', 'description' => 'Anmeldung', 'created_at' => '2026-01-15 08:00:00', 'updated_at' => null],
    ]);

    $migration = require database_path('migrations/2026_10_12_083108_convert_activity_log_timestamps_to_club_timezone.php');
    $migration->up();

    expect(DB::table('tb_activity_log')->orderBy('activityLogID')->pluck('created_at')->all())
        ->toBe(['2026-07-01 10:00:00', '2026-01-15 09:00:00']);

    $migration->down();

    expect(DB::table('tb_activity_log')->orderBy('activityLogID')->pluck('created_at')->all())
        ->toBe(['2026-07-01 08:00:00', '2026-01-15 08:00:00']);
});
