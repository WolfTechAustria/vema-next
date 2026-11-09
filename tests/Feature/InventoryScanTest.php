<?php

use App\Livewire\Inventory\Form as InventoryForm;
use App\Livewire\Inventory\Scan;
use App\Models\ActivityLog;
use App\Models\InventoryItem;
use App\Models\InventoryLoan;
use App\Models\Member;
use App\Models\User;
use App\Services\InventoryScanner;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->zeugwart = User::factory()->create();
    $this->zeugwart->assignRole(Role::findOrCreate('zeugwart', 'web'));
    $this->actingAs($this->zeugwart);

    $this->anna = Member::create(['gender' => 'w', 'name' => 'Anna', 'surname' => 'Huber', 'active' => 1]);
});

it('opens the quick issue page for zeugwarte and admins only', function () {
    $this->get(route('inventory.scan'))->assertOk()->assertSee('Schnellausgabe');
    $this->actingAs(User::factory()->admin()->create())->get(route('inventory.scan'))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('inventory.scan'))->assertForbidden();
});

it('finds items by code or inventory number ignoring case and separators', function () {
    $tag = InventoryItem::factory()->create(['code' => '04:A2:1B:9C', 'inventory_number' => 'T-1']);
    $numbered = InventoryItem::factory()->create(['code' => null, 'inventory_number' => 'Tr-012']);
    $scanner = app(InventoryScanner::class);

    expect($scanner->find("04a21b9c\n")?->itemID)->toBe($tag->itemID)
        ->and($scanner->find(' 04-A2-1B-9C ')?->itemID)->toBe($tag->itemID)
        ->and($scanner->find('tr012')?->itemID)->toBe($numbered->itemID)
        ->and($scanner->find('T-1')?->itemID)->toBe($tag->itemID)
        ->and($scanner->find('unbekannt'))->toBeNull()
        ->and($scanner->find('   '))->toBeNull();
});

it('issues every scanned item to the selected member', function () {
    $items = collect(['4001', '4002', '4003'])->map(fn (string $code) => InventoryItem::factory()->create(['code' => $code]));

    $component = Livewire::test(Scan::class)
        ->call('selectMember', $this->anna->memberID)
        ->set('issuedOn', '2026-11-01')
        ->set('dueOn', '2026-12-31');

    foreach (['4001', '4002'] as $code) {
        $component->set('code', $code)->call('scan');
    }

    $component->call('scan', '4003')
        ->assertSet('code', '')
        ->assertSet('lastResult.status', 'issued')
        ->assertDispatched('scan-result', ok: true)
        ->assertSee($items[0]->label());

    $loans = InventoryLoan::all();

    expect($loans)->toHaveCount(3)
        ->and($loans->pluck('memberID')->unique()->all())->toBe([$this->anna->memberID])
        ->and($loans->first()->issued_on->toDateString())->toBe('2026-11-01')
        ->and($loans->first()->due_on->toDateString())->toBe('2026-12-31')
        ->and(ActivityLog::where('action', 'member.inventory_issued')->where('subject_id', $this->anna->memberID)->count())->toBe(3);
});

it('preselects the member from the url', function () {
    Livewire::withQueryParams(['member' => $this->anna->memberID])
        ->test(Scan::class)
        ->assertSet('memberID', $this->anna->memberID)
        ->assertSee('Anna Huber');
});

it('asks for a member before scanning', function () {
    InventoryItem::factory()->create(['code' => '4001']);

    Livewire::test(Scan::class)
        ->call('scan', '4001')
        ->assertSet('lastResult.message', 'Bitte zuerst ein Mitglied wählen.')
        ->assertDispatched('scan-result', ok: false);

    expect(InventoryLoan::count())->toBe(0);
});

it('offers to create unknown codes with the code prefilled', function () {
    Livewire::test(Scan::class)
        ->call('selectMember', $this->anna->memberID)
        ->call('scan', 'NEU-77')
        ->assertSet('lastResult.status', 'not_found')
        ->assertSee(route('inventory.create', ['code' => 'NEU-77']), false);

    Livewire::withQueryParams(['code' => 'NEU-77'])
        ->test(InventoryForm::class)
        ->assertSet('code', 'NEU-77');
});

it('refuses items held by another member or retired items', function () {
    $max = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);
    $lent = InventoryItem::factory()->create(['code' => '5001']);
    InventoryLoan::create(['itemID' => $lent->itemID, 'memberID' => $max->memberID, 'issued_on' => '2026-01-01']);
    InventoryItem::factory()->retired()->create(['code' => '5002']);

    Livewire::test(Scan::class)
        ->call('selectMember', $this->anna->memberID)
        ->call('scan', '5001')
        ->assertSet('lastResult.status', 'error')
        ->assertSee('ist bei Max Muster ausgegeben')
        ->call('scan', '5002')
        ->assertSee('ist ausgeschieden');

    expect(InventoryLoan::count())->toBe(1);
});

it('takes back an item that is already with the member', function () {
    $item = InventoryItem::factory()->create(['code' => '6001']);
    $loan = InventoryLoan::create(['itemID' => $item->itemID, 'memberID' => $this->anna->memberID, 'issued_on' => '2026-01-01']);

    Livewire::test(Scan::class)
        ->call('selectMember', $this->anna->memberID)
        ->call('scan', '6001')
        ->assertSet('lastResult.status', 'already')
        ->assertSet('lastResult.loanID', $loan->loanID)
        ->call('returnItem', $loan->loanID)
        ->assertSet('lastResult.status', 'returned')
        ->assertSee('zurückgenommen');

    expect($loan->fresh()->returned_on)->not->toBeNull();
});

it('ignores the same code scanned twice in quick succession', function () {
    InventoryItem::factory()->create(['code' => '7001']);

    Livewire::test(Scan::class)
        ->call('selectMember', $this->anna->memberID)
        ->call('scan', '7001')
        ->call('scan', '7001')
        ->assertSet('lastResult.status', 'issued');

    expect(InventoryLoan::count())->toBe(1);
});

it('stores a unique code on the item and logs it', function () {
    InventoryItem::factory()->create(['code' => 'ABC']);

    Livewire::test(InventoryForm::class)
        ->set('name', 'Scan-Trompete')
        ->set('code', 'ABC')
        ->call('save')
        ->assertHasErrors('code')
        ->set('code', ' 04:A2:1B ')
        ->call('save')
        ->assertHasNoErrors();

    $item = InventoryItem::where('name', 'Scan-Trompete')->sole();

    expect($item->code)->toBe('04:A2:1B');

    $log = ActivityLog::where('action', 'inventory.created')->where('subject_id', $item->itemID)->sole();
    expect(collect($log->changes)->pluck('new', 'field')['Barcode/NFC'])->toBe('04:A2:1B');
});
