<?php

use App\Enums\InventoryCondition;
use App\Livewire\Inventory\Show as InventoryShow;
use App\Livewire\MemberPortal\MyInventory;
use App\Livewire\Members\Index as MembersIndex;
use App\Livewire\Members\Show as MembersShow;
use App\Models\ActivityLog;
use App\Models\InventoryItem;
use App\Models\InventoryLoan;
use App\Models\Member;
use App\Models\User;
use App\Services\MemberColumns;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->zeugwart = User::factory()->create();
    $this->zeugwart->assignRole(Role::findOrCreate('zeugwart', 'web'));

    $this->anna = Member::create(['gender' => 'w', 'name' => 'Anna', 'surname' => 'Huber', 'active' => 1]);
    $this->item = InventoryItem::factory()->create(['name' => 'Tracht Damen', 'inventory_number' => 'T-7']);
});

it('issues an item to a member and takes it back', function () {
    $this->actingAs($this->zeugwart);

    Livewire::test(InventoryShow::class, ['item' => $this->item])
        ->call('openLoanForm')
        ->set('loanMemberSearch', 'Hub')
        ->assertSee('Anna Huber')
        ->call('selectLoanMember', $this->anna->memberID)
        ->set('loanIssuedOn', '2026-05-01')
        ->set('loanDueOn', '2026-10-31')
        ->call('issue')
        ->assertHasNoErrors()
        ->assertSee('Ausgegeben an');

    $loan = InventoryLoan::query()->sole();
    expect($loan->memberID)->toBe($this->anna->memberID)
        ->and($loan->due_on->toDateString())->toBe('2026-10-31');

    $memberLog = ActivityLog::where('action', 'member.inventory_issued')->sole();
    expect($memberLog->subject_type)->toBe('Member')
        ->and($memberLog->subject_id)->toBe($this->anna->memberID)
        ->and($memberLog->changes[0]['new'])->toBe('Tracht Damen (T-7), ab 01.05.2026, bis 31.10.2026');

    Livewire::test(InventoryShow::class, ['item' => $this->item->fresh()])
        ->call('openReturnForm')
        ->set('returnedOn', '2026-11-02')
        ->set('returnCondition', InventoryCondition::Used->value)
        ->call('returnItem')
        ->assertHasNoErrors()
        ->assertSee('Frühere Ausgaben');

    expect($loan->fresh()->returned_on->toDateString())->toBe('2026-11-02')
        ->and($this->item->fresh()->condition)->toBe(InventoryCondition::Used)
        ->and(ActivityLog::where('action', 'member.inventory_returned')->where('subject_id', $this->anna->memberID)->exists())->toBeTrue();
});

it('does not issue an item twice or a retired item', function () {
    $this->actingAs($this->zeugwart);
    $max = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);

    InventoryLoan::create(['itemID' => $this->item->itemID, 'memberID' => $this->anna->memberID, 'issued_on' => '2026-01-01']);

    Livewire::test(InventoryShow::class, ['item' => $this->item])
        ->set('showLoanForm', true)
        ->set('loanMemberID', $max->memberID)
        ->set('loanIssuedOn', '2026-02-01')
        ->call('issue')
        ->assertHasErrors('loanMemberID');

    $retired = InventoryItem::factory()->retired()->create();

    Livewire::test(InventoryShow::class, ['item' => $retired])
        ->set('loanMemberID', $max->memberID)
        ->set('loanIssuedOn', '2026-02-01')
        ->call('issue')
        ->assertHasErrors('loanMemberID');

    expect(InventoryLoan::count())->toBe(1);
});

it('shows and issues material on the member page for zeugwarte only', function () {
    Livewire::actingAs($this->zeugwart)
        ->test(MembersShow::class, ['member' => $this->anna])
        ->assertSee('Diesem Mitglied wurde kein Material ausgegeben.')
        ->call('openInventoryForm')
        ->assertSee('Tracht Damen (T-7)')
        ->set('inventoryItemID', $this->item->itemID)
        ->call('issueInventory')
        ->assertHasNoErrors()
        ->assertSee('Ausgegeben');

    $loan = InventoryLoan::query()->sole();

    Livewire::actingAs($this->zeugwart)
        ->test(MembersShow::class, ['member' => $this->anna])
        ->call('returnInventory', $loan->loanID)
        ->assertSee('Zurückgegeben');

    expect($loan->fresh()->returned_on)->not->toBeNull();

    Livewire::actingAs(User::factory()->create())
        ->test(MembersShow::class, ['member' => $this->anna])
        ->assertDontSee('An dieses Mitglied ausgegebenes Material')
        ->call('openInventoryForm')
        ->assertForbidden();
});

it('warns when a former member still has material', function () {
    $this->anna->update(['active' => 0]);
    InventoryLoan::create(['itemID' => $this->item->itemID, 'memberID' => $this->anna->memberID, 'issued_on' => '2026-01-01']);

    Livewire::actingAs($this->zeugwart)
        ->test(MembersShow::class, ['member' => $this->anna])
        ->assertSee('Ausgetretenes Mitglied hat noch Material des Vereins.');
});

it('offers a material column in the member list for zeugwarte', function () {
    InventoryLoan::create(['itemID' => $this->item->itemID, 'memberID' => $this->anna->memberID, 'issued_on' => '2026-01-01']);
    $this->actingAs($this->zeugwart);
    app(MemberColumns::class)->save($this->zeugwart, ['status', 'inventory']);

    Livewire::test(MembersIndex::class)
        ->assertSee('Tracht Damen (T-7)');

    $this->actingAs(User::factory()->create());
    expect(app(MemberColumns::class)->catalog())->not->toHaveKey('inventory');
});

it('shows members only their own material in the portal', function () {
    $member = loginPortalMember('Gruber');
    $other = InventoryItem::factory()->create(['name' => 'Fremde Trompete']);

    InventoryLoan::create(['itemID' => $this->item->itemID, 'memberID' => $member->memberID, 'issued_on' => '2026-01-01', 'due_on' => '2026-12-31']);
    InventoryLoan::create(['itemID' => $other->itemID, 'memberID' => $this->anna->memberID, 'issued_on' => '2026-01-01']);

    Livewire::test(MyInventory::class)
        ->assertSee('Tracht Damen (T-7)')
        ->assertSee('31.12.2026')
        ->assertDontSee('Fremde Trompete');

    $this->get(route('member.profile'))->assertSee(route('member.inventory'));
});

it('serves item photos to the portal only for own material', function () {
    Storage::fake('local');
    $member = loginPortalMember('Gruber');

    Storage::disk('local')->put('inventory/1/foto.jpg', 'jpeg');
    $this->item->update(['photo_path' => 'inventory/1/foto.jpg']);

    $this->get(route('member.inventory.photo', $this->item))->assertNotFound();

    InventoryLoan::create(['itemID' => $this->item->itemID, 'memberID' => $member->memberID, 'issued_on' => '2026-01-01']);

    $this->get(route('member.inventory.photo', $this->item))->assertOk();
});
