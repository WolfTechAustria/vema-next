<?php

use App\Enums\CashBookEntryType;
use App\Livewire\CashBook\Index;
use App\Livewire\CashBook\Years;
use App\Models\CashBookAttachment;
use App\Models\CashBookEntry;
use App\Models\CashBookYear;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    setUpCashBookSchema();

    Storage::fake('local');

    $this->user = createStaffUser();
    $this->actingAs($this->user);

    $this->year = CashBookYear::factory()->create([
        'name' => '2026/27',
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->subMonth()->addYear()->subDay()->toDateString(),
        'opening_balance' => 200,
    ]);
});

describe('recording entries', function () {
    it('records an expense with a receipt photo', function () {
        Livewire::test(Index::class)
            ->call('openCreate', 'expense')
            ->assertSet('date', today()->toDateString())
            ->set('amount', '12,50')
            ->set('description', 'Getränkeeinkauf')
            ->set('category', 'Veranstaltung')
            ->set('newFiles', [UploadedFile::fake()->image('bon.jpg')])
            ->assertCount('files', 1)
            ->call('saveEntry')
            ->assertHasNoErrors()
            ->assertSet('showEntryDialog', false);

        $entry = CashBookEntry::sole();

        expect($entry->type)->toBe(CashBookEntryType::Expense)
            ->and((float) $entry->amount)->toBe(12.5)
            ->and($entry->receipt_number)->toBe(1)
            ->and($entry->category)->toBe('Veranstaltung')
            ->and($entry->created_by)->toBe($this->user->id)
            ->and($entry->attachments)->toHaveCount(1);

        Storage::disk('local')->assertExists($entry->attachments->first()->file_path);
        expect($this->year->balance())->toBe(187.5);
    });

    it('numbers receipts consecutively per year', function () {
        CashBookEntry::factory()->for($this->year, 'year')->create();
        CashBookEntry::factory()->for($this->year, 'year')->create();

        Livewire::test(Index::class)
            ->call('openCreate', 'income')
            ->set('amount', '50')
            ->set('description', 'Spende')
            ->call('saveEntry')
            ->assertHasNoErrors();

        expect(CashBookEntry::latest('cashBookEntryID')->first()->receipt_number)->toBe(3);
    });

    it('rejects dates outside of the club year', function () {
        Livewire::test(Index::class)
            ->call('openCreate', 'income')
            ->set('date', $this->year->end_date->copy()->addDay()->toDateString())
            ->set('amount', '10')
            ->set('description', 'Zu spät')
            ->call('saveEntry')
            ->assertHasErrors(['date' => 'before_or_equal']);

        expect(CashBookEntry::count())->toBe(0);
    });

    it('rejects invalid amounts', function (string $amount) {
        Livewire::test(Index::class)
            ->call('openCreate', 'expense')
            ->set('amount', $amount)
            ->set('description', 'Test')
            ->call('saveEntry')
            ->assertHasErrors('amount');
    })->with(['abc', '0', '-5']);

    it('rejects unsupported file types', function () {
        Livewire::test(Index::class)
            ->call('openCreate', 'expense')
            ->set('newFiles', [UploadedFile::fake()->create('virus.exe', 10)])
            ->assertHasErrors('newFiles.*')
            ->assertCount('files', 0);
    });

    it('updates an existing entry', function () {
        $entry = CashBookEntry::factory()->for($this->year, 'year')->income(10)->create();

        Livewire::test(Index::class)
            ->call('openEdit', $entry->cashBookEntryID)
            ->assertSet('amount', '10,00')
            ->set('amount', '15,00')
            ->set('type', 'expense')
            ->call('saveEntry')
            ->assertHasNoErrors();

        expect((float) $entry->refresh()->amount)->toBe(15.0)
            ->and($entry->type)->toBe(CashBookEntryType::Expense)
            ->and($entry->receipt_number)->toBe(1);
    });

    it('deletes an entry together with its receipts', function () {
        $entry = CashBookEntry::factory()->for($this->year, 'year')->create();
        $attachment = CashBookAttachment::factory()->for($entry, 'entry')->create();
        Storage::disk('local')->put($attachment->file_path, 'x');

        Livewire::test(Index::class)->call('deleteEntry', $entry->cashBookEntryID);

        expect(CashBookEntry::count())->toBe(0)
            ->and(CashBookAttachment::count())->toBe(0);
        Storage::disk('local')->assertMissing($attachment->file_path);
    });
});

describe('closed years', function () {
    beforeEach(function () {
        $this->year->update(['closed_at' => now(), 'closing_balance' => 200]);
    });

    it('prevents new entries', function () {
        Livewire::test(Index::class)
            ->call('openCreate', 'income')
            ->assertHasErrors('year')
            ->assertSet('showEntryDialog', false);
    });

    it('prevents changing or deleting entries', function () {
        $entry = CashBookEntry::factory()->for($this->year, 'year')->income(10)->create();

        Livewire::test(Index::class)
            ->call('openEdit', $entry->cashBookEntryID)
            ->assertSet('showEntryDialog', true)
            ->set('amount', '99')
            ->call('saveEntry')
            ->assertHasErrors('year')
            ->call('deleteEntry', $entry->cashBookEntryID)
            ->assertHasErrors('year');

        expect((float) $entry->refresh()->amount)->toBe(10.0);
    });
});

describe('club years', function () {
    it('closes a year via the years page', function () {
        CashBookEntry::factory()->for($this->year, 'year')->income(100)->create();

        Livewire::test(Years::class)
            ->call('openClose', $this->year->cashBookYearID)
            ->set('generalMeetingDate', '2027-04-10')
            ->call('closeYear')
            ->assertHasNoErrors();

        expect($this->year->refresh()->isClosed())->toBeTrue()
            ->and($this->year->closed_by)->toBe($this->user->id)
            ->and(CashBookYear::count())->toBe(2)
            ->and((float) CashBookYear::open()->sole()->opening_balance)->toBe(300.0);
    });

    it('rejects overlapping years', function () {
        Livewire::test(Years::class)
            ->call('openCreate')
            ->set('startDate', $this->year->end_date->toDateString())
            ->call('saveYear')
            ->assertHasErrors('startDate');
    });

    it('suggests the following period when creating a year', function () {
        Livewire::test(Years::class)
            ->call('openCreate')
            ->assertSet('startDate', $this->year->end_date->copy()->addDay()->toDateString())
            ->assertSet('openingBalance', '200,00')
            ->call('saveYear')
            ->assertHasNoErrors();

        expect(CashBookYear::count())->toBe(2);
    });
});

describe('access', function () {
    it('denies users without the kassier or admin role', function () {
        $this->actingAs(createStaffUser([]));

        $this->get(route('cash-book.index'))->assertForbidden();
        $this->get(route('cash-book.report', $this->year))->assertForbidden();
    });

    it('serves receipts to the kassier', function () {
        $entry = CashBookEntry::factory()->for($this->year, 'year')->create();
        $attachment = CashBookAttachment::factory()->for($entry, 'entry')->create();
        Storage::disk('local')->put($attachment->file_path, 'image-content');

        $this->get(route('cash-book.attachments.show', $attachment))->assertOk();
    });

    it('renders the report pdf', function () {
        CashBookEntry::factory()->for($this->year, 'year')->income(100)->create(['category' => 'Spenden']);

        $this->get(route('cash-book.report', $this->year))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    });
});
