<?php

use App\Livewire\Members\Show;
use App\Models\BoardFunction;
use App\Models\Member;
use App\Models\MemberBoardFunctionAssignment;
use App\Models\User;
use App\Services\BoardFunctionService;
use Livewire\Livewire;

function functionID(string $name): int
{
    return BoardFunction::where('name', $name)->value('boardFunctionID');
}

function functionMember(): Member
{
    return Member::create(['gender' => 'm', 'name' => 'Franz', 'surname' => 'Huber', 'active' => 1]);
}

function openFunctionNames(Member $member): array
{
    return app(BoardFunctionService::class)->currentFunctionsFor($member)
        ->map(fn ($assignment) => $assignment->boardFunction->name)
        ->all();
}

it('stores Schützenrat as its own assignment when assigning Kassier Stv.', function () {
    $member = functionMember();

    app(BoardFunctionService::class)->assign($member, functionID('Kassier Stv.'), '2024-03-01');

    expect(openFunctionNames($member))->toBe(['Kassier Stv.', 'Schützenrat']);

    $schuetzenrat = $member->boardFunctionAssignments()->where('boardFunctionID', functionID('Schützenrat'))->sole();
    expect($schuetzenrat->date_from->format('Y-m-d'))->toBe('2024-03-01');
});

it('keeps Schützenrat when Kassier Stv. ends', function () {
    $member = functionMember();
    $service = app(BoardFunctionService::class);
    $kassierStv = $service->assign($member, functionID('Kassier Stv.'), '2024-03-01');

    $service->end($kassierStv, '2026-03-01');

    expect(openFunctionNames($member))->toBe(['Schützenrat'])
        ->and($service->formerFunctionsFor($member)->sole()->boardFunction->name)->toBe('Kassier Stv.');
});

it('does not add a second Schützenrat or one for top board members', function () {
    $service = app(BoardFunctionService::class);

    $councillor = functionMember();
    $service->assign($councillor, functionID('Schützenrat'), '2020-01-01');
    $service->assign($councillor, functionID('Kassier'), '2024-01-01');

    $chairman = functionMember();
    $service->assign($chairman, functionID('Oberschützenmeister'), '2024-01-01');
    $service->assign($chairman, functionID('Schriftführer'), '2024-01-01');

    expect(openFunctionNames($councillor))->toBe(['Kassier', 'Schützenrat'])
        ->and(openFunctionNames($chairman))->toBe(['Oberschützenmeister', 'Schriftführer']);
});

it('shows former functions with their period on the member page', function () {
    $member = functionMember();
    MemberBoardFunctionAssignment::create([
        'memberID' => $member->memberID,
        'boardFunctionID' => functionID('Oberschützenmeister'),
        'date_from' => '2022-04-01',
        'date_to' => '2024-04-01',
    ]);

    $this->actingAs(User::factory()->create());

    Livewire::test(Show::class, ['member' => $member])
        ->assertSee('Frühere Funktionen')
        ->assertSee('Oberschützenmeister')
        ->assertSee('01.04.2022')
        ->assertSee('01.04.2024');
});

it('backfills Schützenrat for members who only had it derived', function () {
    $member = functionMember();
    MemberBoardFunctionAssignment::create([
        'memberID' => $member->memberID,
        'boardFunctionID' => functionID('Kassier'),
        'date_from' => '2023-05-01',
    ]);

    $migration = require base_path('database/migrations/2026_10_18_111349_backfill_explicit_schuetzenrat_assignments.php');
    $migration->up();

    expect(openFunctionNames($member))->toBe(['Kassier', 'Schützenrat']);
});
