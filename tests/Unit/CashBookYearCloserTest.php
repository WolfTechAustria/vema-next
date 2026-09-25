<?php

use App\Models\CashBookEntry;
use App\Models\CashBookYear;
use App\Services\CashBookYearCloser;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    setUpCashBookSchema();
});

describe('closing a club year', function () {
    it('stores the closing balance and locks the year', function () {
        $year = CashBookYear::factory()->create(['opening_balance' => 1000]);
        CashBookEntry::factory()->for($year, 'year')->income(250.50)->create();
        CashBookEntry::factory()->for($year, 'year')->expense(100.25)->create();

        $closed = app(CashBookYearCloser::class)->close($year, Carbon::parse('2026-04-12'), 'Entlastung erteilt');

        expect($closed->isClosed())->toBeTrue()
            ->and((float) $closed->closing_balance)->toBe(1150.25)
            ->and($closed->general_meeting_date->toDateString())->toBe('2026-04-12')
            ->and($closed->closing_note)->toBe('Entlastung erteilt');
    });

    it('creates the following year with the closing balance as opening balance', function () {
        $year = CashBookYear::factory()->create([
            'name' => '2025/26',
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'opening_balance' => 500,
        ]);
        CashBookEntry::factory()->for($year, 'year')->income(80)->create();

        app(CashBookYearCloser::class)->close($year, Carbon::parse('2026-04-12'));

        $nextYear = CashBookYear::whereKeyNot($year->cashBookYearID)->sole();

        expect($nextYear->name)->toBe('2026/27')
            ->and($nextYear->start_date->toDateString())->toBe('2026-04-01')
            ->and($nextYear->end_date->toDateString())->toBe('2027-03-31')
            ->and((float) $nextYear->opening_balance)->toBe(580.0)
            ->and($nextYear->isClosed())->toBeFalse();
    });

    it('updates the opening balance of an existing open following year', function () {
        $year = CashBookYear::factory()->create([
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'opening_balance' => 100,
        ]);
        $nextYear = CashBookYear::factory()->create([
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'opening_balance' => 0,
        ]);
        CashBookEntry::factory()->for($year, 'year')->expense(30)->create();

        app(CashBookYearCloser::class)->close($year, Carbon::parse('2026-04-12'));

        expect(CashBookYear::count())->toBe(2)
            ->and((float) $nextYear->refresh()->opening_balance)->toBe(70.0);
    });

    it('refuses to close a year twice', function () {
        $year = CashBookYear::factory()->closed()->create();

        app(CashBookYearCloser::class)->close($year, Carbon::parse('2026-04-12'));
    })->throws(RuntimeException::class, 'bereits abgeschlossen');
});

it('names years spanning two calendar years with a slash', function (string $start, string $end, string $expected) {
    expect(CashBookYearCloser::defaultName(Carbon::parse($start), Carbon::parse($end)))->toBe($expected);
})->with([
    ['2025-04-01', '2026-03-31', '2025/26'],
    ['2026-01-01', '2026-12-31', '2026'],
]);
