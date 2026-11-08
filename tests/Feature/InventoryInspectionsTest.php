<?php

use App\Enums\DashboardModule;
use App\Enums\InventoryCondition;
use App\Enums\InventoryInspectionResult;
use App\Livewire\Inventory\Show as InventoryShow;
use App\Mail\InventoryReminderMail;
use App\Models\ActivityLog;
use App\Models\InventoryItem;
use App\Models\InventoryLoan;
use App\Models\Member;
use App\Models\User;
use App\Services\DashboardLayout;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->zeugwart = User::factory()->create(['email' => 'zeugwart@example.test']);
    $this->zeugwart->assignRole(Role::findOrCreate('zeugwart', 'web'));
});

it('records an inspection and schedules the next one from the interval', function () {
    $item = InventoryItem::factory()->inspectionDueIn(-5, 24)->create();

    Livewire::actingAs($this->zeugwart)
        ->test(InventoryShow::class, ['item' => $item])
        ->call('openInspectionForm')
        ->set('inspectedOn', '2026-11-01')
        ->set('inspectionNote', 'Service Fa. Huber')
        ->call('recordInspection')
        ->assertHasNoErrors()
        ->assertSee('Service Fa. Huber');

    expect($item->fresh()->next_inspection_on->toDateString())->toBe('2028-11-01');

    $changes = collect(ActivityLog::where('action', 'inventory.inspected')->sole()->changes)->keyBy('field');
    expect($changes['Prüfung']['new'])->toBe('01.11.2026, In Ordnung, Service Fa. Huber')
        ->and($changes['Nächste Prüfung']['new'])->toBe('01.11.2028');
});

it('uses a manual next date and updates the condition from the result', function () {
    $item = InventoryItem::factory()->create();

    Livewire::actingAs($this->zeugwart)
        ->test(InventoryShow::class, ['item' => $item])
        ->set('inspectedOn', '2026-11-01')
        ->set('inspectionResult', InventoryInspectionResult::Defects->value)
        ->set('inspectionNextOn', '2026-12-01')
        ->call('recordInspection');

    expect($item->fresh()->next_inspection_on->toDateString())->toBe('2026-12-01')
        ->and($item->fresh()->condition)->toBe(InventoryCondition::Defective);

    Livewire::actingAs($this->zeugwart)
        ->test(InventoryShow::class, ['item' => $item->fresh()])
        ->set('inspectedOn', '2026-12-01')
        ->set('inspectionResult', InventoryInspectionResult::Retired->value)
        ->call('recordInspection');

    expect($item->fresh()->condition)->toBe(InventoryCondition::Retired)
        ->and($item->fresh()->next_inspection_on)->toBeNull();
});

it('sends one collective reminder to admins and zeugwarte and not twice', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create(['email' => 'admin@example.test']);
    User::factory()->create(['email' => 'other@example.test']);

    $due = InventoryItem::factory()->inspectionDueIn(7)->create(['name' => 'Gewehr']);
    InventoryItem::factory()->inspectionDueIn(60)->create(['name' => 'Später']);
    $member = Member::create(['gender' => 'w', 'name' => 'Anna', 'surname' => 'Huber', 'active' => 1]);
    InventoryLoan::create(['itemID' => InventoryItem::factory()->create(['name' => 'Zelt'])->itemID, 'memberID' => $member->memberID, 'issued_on' => today()->subMonth(), 'due_on' => today()->subDay()]);

    $this->artisan('inventory:send-reminders')->assertSuccessful();

    Mail::assertSent(InventoryReminderMail::class, function (InventoryReminderMail $mail) use ($admin, $due) {
        return $mail->hasTo($admin->email)
            && $mail->hasTo('zeugwart@example.test')
            && ! $mail->hasTo('other@example.test')
            && $mail->inspections->pluck('itemID')->all() === [$due->itemID]
            && $mail->returns->count() === 1;
    });
    Mail::assertSentCount(1);

    $this->artisan('inventory:send-reminders')->assertSuccessful();
    Mail::assertSentCount(1);

    // Nach erfasster Prüfung mit neuem Termin wird wieder erinnert, sobald er fällig ist.
    $due->update(['next_inspection_on' => today()->addDays(3)]);
    $this->artisan('inventory:send-reminders')->assertSuccessful();
    Mail::assertSentCount(2);
});

it('shows the dashboard module to admins and zeugwarte', function () {
    InventoryItem::factory()->inspectionDueIn(2)->create(['name' => 'Gewehr']);

    expect(DashboardModule::Inventory->isAvailableFor($this->zeugwart))->toBeTrue()
        ->and(DashboardModule::Inventory->isAvailableFor(User::factory()->create()))->toBeFalse();

    app(DashboardLayout::class)->saveClubDefault(['inventory']);

    $this->actingAs($this->zeugwart)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Prüfung fällig')
        ->assertSee('Gewehr');
});
