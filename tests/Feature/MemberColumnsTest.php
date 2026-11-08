<?php

use App\Enums\MemberFieldPortal;
use App\Enums\MemberFieldType;
use App\Exports\MembersExport;
use App\Livewire\Members\Index as MembersIndex;
use App\Models\Member;
use App\Models\MemberField;
use App\Models\User;
use App\Services\MemberColumns;
use App\Services\MemberFields;
use App\Services\MemberHonors;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();

    $this->anna = Member::create(['gender' => 'w', 'name' => 'Anna', 'surname' => 'Huber', 'active' => 1, 'street' => 'Dorfstraße 1', 'dateOfJoin' => '2010-04-01']);
    $this->max = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1, 'street' => 'Bachweg 7']);

    $this->field = MemberField::create([
        'name' => 'Instrument', 'key' => 'instrument', 'type' => MemberFieldType::Text,
        'portal' => MemberFieldPortal::Hidden, 'sort_order' => 1, 'active' => true,
    ]);
    app(MemberFields::class)->save($this->anna, [$this->field->fieldID => 'Tuba']);
    app(MemberHonors::class)->add($this->anna, 'Ehrennadel in Gold', '2024-05-03', null);
});

it('shows status and date of join by default', function () {
    Livewire::actingAs($this->admin)
        ->test(MembersIndex::class)
        ->assertSeeInOrder(['Status', 'Eintritt'])
        ->assertSee('01.04.2010')
        ->assertDontSee('Dorfstraße 1');
});

it('shows, hides and orders columns per user', function () {
    $component = Livewire::actingAs($this->admin)
        ->test(MembersIndex::class)
        ->call('toggleColumn', 'street')
        ->call('toggleColumn', 'field_'.$this->field->fieldID)
        ->call('toggleColumn', 'honors')
        ->call('toggleColumn', 'date_of_join')
        ->call('moveColumn', 'honors', -1)
        ->assertSee('Dorfstraße 1')
        ->assertSee('Tuba')
        ->assertSee('Ehrennadel in Gold (2024)');

    expect(app(MemberColumns::class)->columnsFor($this->admin))
        ->toBe(['status', 'street', 'honors', 'field_'.$this->field->fieldID]);

    // Andere Benutzer sehen weiter den Standard.
    $other = User::factory()->create();

    expect(app(MemberColumns::class)->columnsFor($other))->toBe(MemberColumns::DEFAULT);

    $component->call('resetColumns');

    expect(app(MemberColumns::class)->columnsFor($this->admin))->toBe(MemberColumns::DEFAULT);
});

it('lets admins save the club default for users without own columns', function () {
    Livewire::actingAs($this->admin)
        ->test(MembersIndex::class)
        ->call('toggleColumn', 'city')
        ->call('saveColumnsAsClubDefault')
        ->assertSee('als Vereinsstandard gespeichert');

    $kassier = User::factory()->create();

    expect(app(MemberColumns::class)->columnsFor($kassier))->toBe(['status', 'date_of_join', 'city']);

    Livewire::actingAs($kassier)
        ->test(MembersIndex::class)
        ->call('saveColumnsAsClubDefault')
        ->assertForbidden();
});

it('ignores unknown and deleted columns', function () {
    app(MemberColumns::class)->save($this->admin, ['status', 'nonsense', 'field_'.$this->field->fieldID]);

    $this->field->delete();

    expect(app(MemberColumns::class)->columnsFor($this->admin))->toBe(['status']);
});

it('exports the filtered members with all columns', function () {
    Excel::fake();

    Livewire::actingAs($this->admin)
        ->test(MembersIndex::class)
        ->set('search', 'Huber')
        ->call('export');

    Excel::assertDownloaded('mitglieder-'.now()->format('Y-m-d').'.xlsx', function (MembersExport $export) {
        $headings = $export->headings();
        $rows = $export->array();
        $row = array_combine($headings, $rows[0]);

        return count($rows) === 1
            && $row['Nachname'] === 'Huber'
            && $row['Straße'] === 'Dorfstraße 1'
            && $row['Instrument'] === 'Tuba'
            && $row['Ehrungen'] === 'Ehrennadel in Gold (2024)'
            && $row['Eintritt'] === '01.04.2010';
    });
});
