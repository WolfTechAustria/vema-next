<?php

use App\Livewire\Members\Index as MembersIndex;
use App\Livewire\Members\Show as MembersShow;
use App\Models\ActivityLog;
use App\Models\Honor;
use App\Models\Member;
use App\Models\MemberHonor;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->anna = Member::create(['gender' => 'w', 'name' => 'Anna', 'surname' => 'Huber', 'active' => 1]);
    $this->max = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);
});

it('adds a typed honor that is then suggested for other members', function () {
    Livewire::test(MembersShow::class, ['member' => $this->anna])
        ->call('openHonorForm')
        ->set('honorName', '  Ehrennadel   in Gold ')
        ->set('honorDate', '2024-05-03')
        ->set('honorNote', 'Landesverband')
        ->call('saveHonor')
        ->assertHasNoErrors()
        ->assertSee('Ehrennadel in Gold')
        ->assertSeeInOrder(['03.05.2024', 'Landesverband']);

    Livewire::test(MembersShow::class, ['member' => $this->max])
        ->call('openHonorForm')
        ->assertSee('<option value="Ehrennadel in Gold">', false)
        ->set('honorName', 'ehrennadel in gold')
        ->call('saveHonor');

    expect(Honor::count())->toBe(1)
        ->and(MemberHonor::count())->toBe(2);

    $log = ActivityLog::query()->where('action', 'member.honor_added')->where('subject_id', $this->anna->memberID)->sole();

    expect($log->changes[0])->toMatchArray(['field' => 'Ehrung', 'new' => 'Ehrennadel in Gold, 03.05.2024, Landesverband']);
});

it('requires a name', function () {
    Livewire::test(MembersShow::class, ['member' => $this->anna])
        ->call('openHonorForm')
        ->call('saveHonor')
        ->assertHasErrors('honorName');
});

it('edits and removes honors and forgets unused suggestions', function () {
    Livewire::test(MembersShow::class, ['member' => $this->anna])
        ->call('openHonorForm')
        ->set('honorName', 'Ehrennadel in Gld')
        ->call('saveHonor');

    $memberHonor = MemberHonor::query()->sole();

    Livewire::test(MembersShow::class, ['member' => $this->anna])
        ->call('openHonorForm', $memberHonor->memberHonorID)
        ->assertSet('honorName', 'Ehrennadel in Gld')
        ->set('honorName', 'Ehrennadel in Gold')
        ->call('saveHonor');

    expect(Honor::query()->pluck('name')->all())->toBe(['Ehrennadel in Gold']);

    $changes = ActivityLog::query()->where('action', 'member.honor_updated')->sole()->changes;

    expect($changes[0])->toMatchArray(['old' => 'Ehrennadel in Gld, '.now()->format('d.m.Y'), 'new' => 'Ehrennadel in Gold, '.now()->format('d.m.Y')]);

    Livewire::test(MembersShow::class, ['member' => $this->anna])
        ->call('removeHonor', $memberHonor->memberHonorID)
        ->assertSee('Keine Ehrung');

    expect(Honor::count())->toBe(0)
        ->and(ActivityLog::query()->where('action', 'member.honor_removed')->exists())->toBeTrue();
});

it('does not touch honors of other members', function () {
    Livewire::test(MembersShow::class, ['member' => $this->max])
        ->call('openHonorForm')
        ->set('honorName', 'Jubiläum 25 Jahre')
        ->call('saveHonor');

    expect(fn () => Livewire::test(MembersShow::class, ['member' => $this->anna])
        ->call('removeHonor', MemberHonor::query()->sole()->memberHonorID))
        ->toThrow(ModelNotFoundException::class);
});

it('filters the member list by honor', function () {
    Livewire::test(MembersShow::class, ['member' => $this->anna])
        ->call('openHonorForm')
        ->set('honorName', 'Ehrennadel in Gold')
        ->call('saveHonor');

    Livewire::test(MembersIndex::class)
        ->set('honorFilter', (string) Honor::query()->sole()->honorID)
        ->assertSee('Huber')
        ->assertDontSee('Muster');
});
