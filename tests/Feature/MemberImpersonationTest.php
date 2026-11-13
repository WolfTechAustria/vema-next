<?php

use App\Http\Controllers\MemberImpersonationController;
use App\Livewire\Members\Show as MembersShow;
use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberEmail;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

function impersonationMember(?string $email = 'anna@example.com', bool $active = true): Member
{
    $member = Member::create(['gender' => 'w', 'name' => 'Anna', 'surname' => 'Huber', 'active' => $active ? 1 : 0]);

    if ($email !== null) {
        MemberEmail::create(['memberID' => $member->memberID, 'email' => $email]);
    }

    return $member;
}

it('lets an admin open the member area as the member and return to the administration', function () {
    $admin = User::factory()->admin()->create();
    $member = impersonationMember();
    $this->actingAs($admin);

    $this->post(route('members.impersonate', $member))->assertRedirect(route('member.profile'));

    $account = MemberAccount::query()->where('email', 'anna@example.com')->sole();

    expect(Auth::guard('member')->id())->toBe($account->accountID)
        ->and(session('active_member_id'))->toBe($member->memberID)
        ->and(ActivityLog::query()->where('action', 'member.impersonation_started')->exists())->toBeTrue();

    $this->get(route('member.profile'))
        ->assertOk()
        ->assertSee('Mitgliederansicht')
        ->assertSee('Zurück zur Verwaltung');

    $this->post(route('member.impersonation.stop'))->assertRedirect(route('members.show', $member));

    expect(Auth::guard('member')->check())->toBeFalse()
        ->and(Auth::guard('web')->id())->toBe($admin->id)
        ->and(session(MemberImpersonationController::SESSION_KEY))->toBeNull();
});

it('uses the email that already has a member account', function () {
    $this->actingAs(User::factory()->admin()->create());
    $member = impersonationMember('neu@example.com');
    MemberEmail::create(['memberID' => $member->memberID, 'email' => 'bestehend@example.com']);
    $account = MemberAccount::create(['memberID' => $member->memberID, 'email' => 'bestehend@example.com']);

    $this->post(route('members.impersonate', $member));

    expect(Auth::guard('member')->id())->toBe($account->accountID)
        ->and(MemberAccount::query()->count())->toBe(1);
});

it('refuses members without email or inactive members', function (?string $email, bool $active) {
    $this->actingAs(User::factory()->admin()->create());
    $member = impersonationMember($email, $active);

    $this->from(route('members.show', $member))
        ->post(route('members.impersonate', $member))
        ->assertRedirect(route('members.show', $member))
        ->assertSessionHas('error');

    expect(Auth::guard('member')->check())->toBeFalse();
})->with([
    'ohne E-Mail' => [null, true],
    'ausgetreten' => ['anna@example.com', false],
]);

it('is only available to admins', function () {
    $member = impersonationMember();
    $this->actingAs(User::factory()->create());

    $this->post(route('members.impersonate', $member))->assertForbidden();

    Livewire::test(MembersShow::class, ['member' => $member])->assertDontSee('Als Mitglied anmelden');

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(MembersShow::class, ['member' => $member])->assertSee('Als Mitglied anmelden');
});

it('keeps the regular logout for members who logged in themselves', function () {
    loginPortalMember();

    $this->post(route('member.impersonation.stop'))->assertRedirect(route('member.profile'));

    $this->get(route('member.profile'))->assertDontSee('Zurück zur Verwaltung');
});
