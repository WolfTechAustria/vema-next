<?php

use App\Livewire\Members\Create as MembersCreate;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    $this->actingAs(User::factory()->admin()->create());
});

it('preselects today as date of join', function () {
    $this->travelTo(now()->setDate(2026, 11, 3)->setTime(10, 0));

    Livewire::test(MembersCreate::class)
        ->assertSet('dateOfJoin', '2026-11-03')
        ->set('gender', 'w')
        ->set('name', 'Anna')
        ->set('surname', 'Neumitglied')
        ->call('save')
        ->assertHasNoErrors();

    expect(Member::query()->sole()->dateOfJoin->toDateString())->toBe('2026-11-03');
});

it('still saves members without date of join when the field is cleared', function () {
    Livewire::test(MembersCreate::class)
        ->set('gender', 'w')
        ->set('name', 'Anna')
        ->set('surname', 'Neumitglied')
        ->set('dateOfJoin', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(Member::query()->sole()->dateOfJoin)->toBeNull();
});
