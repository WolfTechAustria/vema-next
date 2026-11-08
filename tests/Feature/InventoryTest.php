<?php

use App\Enums\InventoryCondition;
use App\Exports\InventoryExport;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Inventory\Form as InventoryForm;
use App\Livewire\Inventory\Index as InventoryIndex;
use App\Livewire\Inventory\Show as InventoryShow;
use App\Models\ActivityLog;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
});

function zeugwart(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('zeugwart', 'web'));

    return $user;
}

describe('access', function () {
    it('lets admins and zeugwarte open the inventory', function () {
        $item = InventoryItem::factory()->create();

        foreach ([zeugwart(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->get(route('inventory.index'))->assertOk()->assertSee($item->name);
            $this->actingAs($user)->get(route('inventory.show', $item))->assertOk();
            $this->actingAs($user)->get(route('inventory.create'))->assertOk();
        }
    });

    it('forbids other staff and hides the link', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('inventory.index'))->assertForbidden();
        $this->actingAs($user)->get(route('dashboard'))->assertDontSee(route('inventory.index'));
        $this->actingAs(zeugwart())->get(route('dashboard'))->assertSee(route('inventory.index'));
    });

    it('grants and revokes the zeugwart role in the user management', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        Livewire::actingAs($admin)->test(UsersIndex::class)
            ->assertSee('Zeugwart')
            ->call('toggleZeugwart', $user->id);

        expect($user->fresh()->hasRole('zeugwart'))->toBeTrue()
            ->and(ActivityLog::where('action', 'user.zeugwart_granted')->exists())->toBeTrue();

        Livewire::actingAs($admin)->test(UsersIndex::class)->call('toggleZeugwart', $user->id);

        expect($user->fresh()->hasRole('zeugwart'))->toBeFalse();
    });
});

describe('items', function () {
    beforeEach(function () {
        $this->actingAs(zeugwart());
    });

    it('creates an item with a typed category that becomes a suggestion', function () {
        Livewire::test(InventoryForm::class)
            ->set('name', 'Tracht Herren Gr. 52')
            ->set('category', '  Tracht ')
            ->set('inventoryNumber', 'T-001')
            ->set('location', 'Probelokal')
            ->set('purchasedOn', '2024-03-01')
            ->set('purchasePrice', '450,50')
            ->set('inspectionIntervalMonths', '12')
            ->set('nextInspectionOn', '2026-03-01')
            ->set('photo', UploadedFile::fake()->image('tracht.jpg', 1600, 1200))
            ->call('save')
            ->assertHasNoErrors();

        $item = InventoryItem::query()->sole();

        expect($item->category->name)->toBe('Tracht')
            ->and((float) $item->purchase_price)->toBe(450.5)
            ->and($item->inspection_interval_months)->toBe(12)
            ->and($item->photo_path)->not->toBeNull();

        Storage::disk('local')->assertExists($item->photo_path);
        [$width] = getimagesizefromstring(Storage::disk('local')->get($item->photo_path));
        expect($width)->toBe(1200);

        Livewire::test(InventoryForm::class)
            ->assertSee('<option value="Tracht">', false)
            ->set('name', 'Tracht Damen')
            ->set('category', 'tracht')
            ->call('save');

        expect(InventoryCategory::count())->toBe(1);

        $log = ActivityLog::where('action', 'inventory.created')->where('subject_id', $item->itemID)->sole();
        expect(collect($log->changes)->pluck('new', 'field')->all())->toMatchArray(['Bezeichnung' => 'Tracht Herren Gr. 52', 'Anschaffungspreis' => '450,50 €']);
    });

    it('validates required fields and unique inventory numbers', function () {
        InventoryItem::factory()->create(['inventory_number' => 'T-001']);

        Livewire::test(InventoryForm::class)
            ->set('inventoryNumber', 'T-001')
            ->set('purchasePrice', 'abc')
            ->call('save')
            ->assertHasErrors(['name', 'inventoryNumber', 'purchasePrice']);
    });

    it('logs changes and forgets unused categories', function () {
        Livewire::test(InventoryForm::class)
            ->set('name', 'Trompete')
            ->set('category', 'Instrumnet')
            ->call('save');

        $item = InventoryItem::query()->sole();

        Livewire::test(InventoryForm::class, ['item' => $item])
            ->assertSet('category', 'Instrumnet')
            ->set('category', 'Instrument')
            ->set('condition', InventoryCondition::Defective->value)
            ->call('save')
            ->assertHasNoErrors();

        expect(InventoryCategory::pluck('name')->all())->toBe(['Instrument']);

        $changes = collect(ActivityLog::where('action', 'inventory.updated')->sole()->changes)->keyBy('field');
        expect($changes['Kategorie'])->toMatchArray(['old' => 'Instrumnet', 'new' => 'Instrument'])
            ->and($changes['Zustand'])->toMatchArray(['old' => 'Gut', 'new' => 'Defekt']);
    });

    it('deletes an item with its photo', function () {
        $item = InventoryItem::factory()->create();
        Storage::disk('local')->put('inventory/'.$item->itemID.'/foto.jpg', 'x');
        $item->update(['photo_path' => 'inventory/'.$item->itemID.'/foto.jpg']);

        Livewire::test(InventoryShow::class, ['item' => $item])
            ->call('delete')
            ->assertRedirect(route('inventory.index'));

        expect(InventoryItem::count())->toBe(0);
        Storage::disk('local')->assertMissing('inventory/'.$item->itemID.'/foto.jpg');
    });

    it('filters the list and hides retired items by default', function () {
        InventoryItem::factory()->create(['name' => 'Trompete']);
        InventoryItem::factory()->retired()->create(['name' => 'Alte Fahne']);
        InventoryItem::factory()->inspectionDueIn(3)->create(['name' => 'Gewehr']);

        Livewire::test(InventoryIndex::class)
            ->assertSee('Trompete')
            ->assertDontSee('Alte Fahne')
            ->set('statusFilter', 'inspection_due')
            ->assertSee('Gewehr')
            ->assertDontSee('Trompete')
            ->set('statusFilter', 'all')
            ->set('conditionFilter', InventoryCondition::Retired->value)
            ->assertSee('Alte Fahne')
            ->assertDontSee('Trompete');
    });

    it('exports the filtered list to Excel', function () {
        Excel::fake();
        InventoryItem::factory()->create(['name' => 'Trompete', 'inventory_number' => 'I-1']);
        InventoryItem::factory()->create(['name' => 'Klarinette', 'inventory_number' => 'I-2']);

        Livewire::test(InventoryIndex::class)
            ->set('search', 'Trompete')
            ->call('export');

        Excel::assertDownloaded('material-'.now()->format('Y-m-d').'.xlsx', function (InventoryExport $export) {
            $rows = $export->array();

            return count($rows) === 1 && array_combine($export->headings(), $rows[0])['Bezeichnung'] === 'Trompete';
        });
    });
});
