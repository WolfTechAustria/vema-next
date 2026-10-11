<?php

use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Models\Invoice;
use App\Models\InvoiceNumberSequence;
use App\Models\Setting;
use App\Models\User;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 11));
    $this->generator = app(InvoiceNumberGenerator::class);
});

/**
 * Bereits finalisierte Rechnung mit dieser Nummer.
 */
function existingInvoice(string $invoiceNumber): void
{
    $recipientID = DB::table('tb_invoice_recipients')->insertGetId(['company_name' => 'Sponsor GmbH']);

    Invoice::query()->create([
        'recipientID' => $recipientID,
        'invoice_number' => $invoiceNumber,
        'invoice_date' => now()->toDateString(),
        'status' => 'sent',
    ]);
}

function nextInvoiceNumber(): string
{
    return DB::transaction(fn () => app(InvoiceNumberGenerator::class)->next(2026));
}

describe('generator', function () {
    it('keeps the previous format by default', function () {
        expect(nextInvoiceNumber())->toBe('2026-0001')
            ->and(nextInvoiceNumber())->toBe('2026-0002');
    });

    it('formats all placeholders', function (string $format, string $expected) {
        expect($this->generator->format($format, 2026, 7))->toBe($expected);
    })->with([
        ['{JAHR}-{NR:4}', '2026-0007'],
        ['{JAHR}{NR:5}', '202600007'],
        ['RE-{JJ}/{NR}', 'RE-26/7'],
        ['{NR:6}', '000007'],
    ]);

    it('continues a custom range after switching mid-year', function () {
        Setting::current()->update(['invoice_number_format' => '{JAHR}{NR:5}']);
        $this->generator->setNextNumber('{JAHR}{NR:5}', 2026, 10001);

        expect(nextInvoiceNumber())->toBe('202610001')
            ->and(nextInvoiceNumber())->toBe('202610002');
    });

    it('counts continuously across years without a year in the format', function () {
        Setting::current()->update(['invoice_number_format' => 'R{NR:5}']);

        $first = DB::transaction(fn () => $this->generator->next(2026));
        $second = DB::transaction(fn () => $this->generator->next(2027));

        expect([$first, $second])->toBe(['R00001', 'R00002'])
            ->and(InvoiceNumberSequence::query()->find(0)->next_number)->toBe(3);
    });

    it('restarts the counter every year with a year in the format', function () {
        nextInvoiceNumber();

        expect(DB::transaction(fn () => $this->generator->next(2027)))->toBe('2027-0001');
    });

    it('skips numbers that are already taken', function () {
        existingInvoice('2026-0001');
        existingInvoice('2026-0002');

        expect(nextInvoiceNumber())->toBe('2026-0003')
            ->and(nextInvoiceNumber())->toBe('2026-0004');
    });

    it('rejects invalid formats', function (string $format) {
        expect($this->generator->validationError($format))->not->toBeNull();
    })->with([
        'without number' => '{JAHR}-',
        'two numbers' => '{NR}-{NR}',
        'unknown placeholder' => '{MONAT}-{NR}',
        'spaces' => 'RE {NR}',
    ]);
});

describe('settings', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->admin()->create());
    });

    it('shows the current format, next number and a preview', function () {
        Livewire::test(SettingsIndex::class)
            ->assertSet('invoice_number_format', '{JAHR}-{NR:4}')
            ->assertSet('invoice_next_number', '1')
            ->assertSee('2026-0001')
            ->set('invoice_number_format', '{JAHR}{NR:5}')
            ->set('invoice_next_number', '10001')
            ->assertSee('202610001');
    });

    it('saves a custom number range', function () {
        Livewire::test(SettingsIndex::class)
            ->set('invoice_number_format', '{JAHR}{NR:5}')
            ->set('invoice_next_number', '10001')
            ->call('save')
            ->assertHasNoErrors();

        expect(Setting::current()->invoice_number_format)->toBe('{JAHR}{NR:5}')
            ->and(nextInvoiceNumber())->toBe('202610001');
    });

    it('does not reset the counter when only other settings change', function () {
        $component = Livewire::test(SettingsIndex::class);

        // Zwischenzeitlich wird eine Rechnung erstellt.
        nextInvoiceNumber();

        $component->set('bank_name', 'Raiffeisenbank')
            ->call('save')
            ->assertHasNoErrors();

        expect(nextInvoiceNumber())->toBe('2026-0002');
    });

    it('rejects invalid formats and numbers that are too long', function (string $format, string $nextNumber) {
        Livewire::test(SettingsIndex::class)
            ->set('invoice_number_format', $format)
            ->set('invoice_next_number', $nextNumber)
            ->call('save')
            ->assertHasErrors('invoice_number_format');

        expect(Setting::current()->fresh()->invoice_number_format)->toBe('{JAHR}-{NR:4}');
    })->with([
        'missing number' => ['{JAHR}-', '1'],
        'too long' => ['VEREINSRECHNUNG-{JAHR}-{NR:8}', '1'],
    ]);
});
