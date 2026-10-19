<?php

use App\Livewire\Admin\Settings\BoardFunctions;
use App\Models\BoardFunction;
use App\Models\Member;
use App\Models\MemberBoardFunctionAssignment;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function boardFunction(string $name): BoardFunction
{
    return BoardFunction::where('name', $name)->sole();
}

test('only admins can open the board function settings', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.settings.board-functions'))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->get(route('admin.settings.board-functions'))
        ->assertOk()
        ->assertSee('Oberschützenmeister');
});

test('renaming a function keeps its assignments and logs the change', function () {
    $member = Member::create(['gender' => 'm', 'name' => 'Franz', 'surname' => 'Huber', 'active' => 1]);
    $assignment = MemberBoardFunctionAssignment::create([
        'memberID' => $member->memberID,
        'boardFunctionID' => boardFunction('Oberschützenmeister')->boardFunctionID,
        'date_from' => '2024-01-01',
    ]);

    Livewire::actingAs($this->admin)
        ->test(BoardFunctions::class)
        ->call('editFunction', $assignment->boardFunctionID)
        ->set('name', 'Obmann')
        ->call('saveFunction')
        ->assertHasNoErrors()
        ->assertSee('Obmann');

    expect($assignment->fresh()->boardFunction->name)->toBe('Obmann')
        ->and(loggedChanges('board_function.updated'))->toBe([
            'Bezeichnung' => ['field' => 'Bezeichnung', 'old' => 'Oberschützenmeister', 'new' => 'Obmann', 'masked' => false],
        ]);
});

test('a new function is added active at the end of the list', function () {
    Livewire::actingAs($this->admin)
        ->test(BoardFunctions::class)
        ->call('newFunction')
        ->set('name', 'Jugendwart')
        ->set('implies_schuetzenrat', true)
        ->call('saveFunction')
        ->assertHasNoErrors();

    $function = boardFunction('Jugendwart');

    expect($function->sort_order)->toBe(9)
        ->and($function->active)->toBeTrue()
        ->and($function->implies_schuetzenrat)->toBeTrue();
});

test('a function needs a unique name', function (string $name, string $message) {
    Livewire::actingAs($this->admin)
        ->test(BoardFunctions::class)
        ->call('newFunction')
        ->set('name', $name)
        ->call('saveFunction')
        ->assertHasErrors('name')
        ->assertSee($message);

    expect(BoardFunction::count())->toBe(8);
})->with([
    'empty' => ['', 'Bitte eine Bezeichnung eingeben.'],
    'duplicate' => ['Kassier', 'Diese Funktion gibt es bereits.'],
]);

test('marking a function as council removes the flag from the previous one', function () {
    Livewire::actingAs($this->admin)
        ->test(BoardFunctions::class)
        ->call('newFunction')
        ->set('name', 'Ausschuss')
        ->set('is_council', true)
        ->call('saveFunction')
        ->assertHasNoErrors();

    expect(BoardFunction::where('is_council', true)->pluck('name')->all())->toBe(['Ausschuss']);
});

test('the council function cannot imply itself', function () {
    Livewire::actingAs($this->admin)
        ->test(BoardFunctions::class)
        ->call('editFunction', boardFunction('Schützenrat')->boardFunctionID)
        ->set('implies_schuetzenrat', true)
        ->call('saveFunction')
        ->assertHasErrors('implies_schuetzenrat')
        ->assertSee('Die Rats-Funktion kann sich nicht selbst zusätzlich zuweisen.');

    expect(boardFunction('Schützenrat')->implies_schuetzenrat)->toBeFalse();
});

test('a function that was ever assigned cannot be deleted', function () {
    $kassier = boardFunction('Kassier');
    $member = Member::create(['gender' => 'm', 'name' => 'Franz', 'surname' => 'Huber', 'active' => 1]);
    MemberBoardFunctionAssignment::create([
        'memberID' => $member->memberID,
        'boardFunctionID' => $kassier->boardFunctionID,
        'date_from' => '2020-01-01',
        'date_to' => '2022-01-01',
    ]);

    Livewire::actingAs($this->admin)
        ->test(BoardFunctions::class)
        ->call('deleteFunction', $kassier->boardFunctionID)
        ->assertSee('Bitte stattdessen deaktivieren.');

    expect(BoardFunction::find($kassier->boardFunctionID))->not->toBeNull();
});

test('an unused function can be deleted', function () {
    $kassierStv = boardFunction('Kassier Stv.');

    Livewire::actingAs($this->admin)
        ->test(BoardFunctions::class)
        ->call('deleteFunction', $kassierStv->boardFunctionID);

    expect(BoardFunction::find($kassierStv->boardFunctionID))->toBeNull();
});

test('moving a function down swaps it with the next one', function () {
    Livewire::actingAs($this->admin)
        ->test(BoardFunctions::class)
        ->call('moveDown', boardFunction('Oberschützenmeister')->boardFunctionID);

    expect(BoardFunction::orderBy('sort_order')->limit(2)->pluck('name')->all())
        ->toBe(['1. Schützenmeister', 'Oberschützenmeister']);
});

test('deactivating a function sets it inactive', function () {
    Livewire::actingAs($this->admin)
        ->test(BoardFunctions::class)
        ->call('toggleActive', boardFunction('Kassier Stv.')->boardFunctionID);

    expect(boardFunction('Kassier Stv.')->active)->toBeFalse();
});
