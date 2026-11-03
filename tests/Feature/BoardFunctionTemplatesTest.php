<?php

use App\Livewire\Admin\Settings\BoardFunctions;
use App\Models\ActivityLog;
use App\Models\BoardFunction;
use App\Models\Member;
use App\Models\MemberBoardFunctionAssignment;
use App\Models\User;
use App\Services\BoardFunctionService;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('replaces the functions by a template while none is assigned', function () {
    Livewire::actingAs($this->admin)
        ->test(BoardFunctions::class)
        ->set('template', 'band')
        ->assertSee('Kapellmeister/in')
        ->call('applyTemplate')
        ->assertSee('Vorlage „Musikkapelle“ übernommen');

    expect(BoardFunction::query()->orderBy('sort_order')->pluck('name')->all())->toBe([
        'Obmann/Obfrau', 'Obmann-Stv.', 'Kapellmeister/in', 'Kapellmeister-Stv.', 'Stabführer/in',
        'Schriftführer', 'Kassier', 'Jugendreferent/in', 'Ausschussmitglied',
    ])
        ->and(BoardFunction::query()->where('is_council', true)->value('name'))->toBe('Ausschussmitglied')
        ->and(ActivityLog::query()->where('action', 'board_function.template_applied')->exists())->toBeTrue();

    // Die Automatik für die Rats-Funktion greift mit den neuen Funktionen.
    $member = Member::create(['gender' => 'w', 'name' => 'Eva', 'surname' => 'Kassierin', 'active' => 1]);
    app(BoardFunctionService::class)->assign($member, BoardFunction::where('name', 'Kassier')->sole()->boardFunctionID, '2026-01-01');

    expect($member->currentBoardFunctionAssignments()->with('boardFunction')->get()->pluck('boardFunction.name')->sort()->values()->all())
        ->toBe(['Ausschussmitglied', 'Kassier']);
});

it('keeps the functions once one is assigned', function () {
    $member = Member::create(['gender' => 'm', 'name' => 'Franz', 'surname' => 'Huber', 'active' => 1]);
    MemberBoardFunctionAssignment::create([
        'memberID' => $member->memberID,
        'boardFunctionID' => BoardFunction::where('name', 'Oberschützenmeister')->sole()->boardFunctionID,
        'date_from' => '2024-01-01',
    ]);

    Livewire::actingAs($this->admin)
        ->test(BoardFunctions::class)
        ->assertSee('Es sind bereits Funktionen an Mitglieder vergeben')
        ->assertDontSee('wire:click="applyTemplate"', false)
        ->set('template', 'general')
        ->call('applyTemplate');

    expect(BoardFunction::where('name', 'Oberschützenmeister')->exists())->toBeTrue()
        ->and(BoardFunction::where('name', 'Obmann/Obfrau')->exists())->toBeFalse();
});

it('lets only admins apply a template', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(BoardFunctions::class)
        ->call('applyTemplate')
        ->assertForbidden();

    expect(BoardFunction::where('name', 'Oberschützenmeister')->exists())->toBeTrue();
});
