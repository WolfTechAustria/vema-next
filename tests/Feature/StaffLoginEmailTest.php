<?php

use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Members\Edit as MembersEdit;
use App\Mail\StaffLoginEmailChangedMail;
use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\MemberEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();

    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin, 'web');
});

/**
 * Funktionär mit verknüpftem Mitglied und den angegebenen Mitglieds-Adressen.
 *
 * @param  list<string>  $memberEmails
 */
function staffWithMember(array $memberEmails, string $loginEmail = 'alt@example.com'): User
{
    $member = Member::create(['gender' => 'w', 'name' => 'Eva', 'surname' => 'Kassier', 'active' => 1]);

    foreach ($memberEmails as $email) {
        MemberEmail::create(['memberID' => $member->memberID, 'email' => $email]);
    }

    return User::factory()->create([
        'username' => 'eva',
        'email' => $loginEmail,
        'password' => bcrypt('geheimes-passwort'),
        'memberID' => $member->memberID,
    ]);
}

it('follows a changed address in the member data and warns the old address', function () {
    $staff = staffWithMember(['alt@example.com']);

    Livewire::test(MembersEdit::class, ['member' => $staff->member])
        ->set('emails.0.email', 'neu@example.com')
        ->call('save')
        ->assertHasNoErrors();

    expect($staff->fresh()->email)->toBe('neu@example.com')
        ->and(session('success'))->toContain('Login-E-Mail von „eva“ ist jetzt neu@example.com');

    Mail::assertSent(StaffLoginEmailChangedMail::class, fn (StaffLoginEmailChangedMail $mail) => $mail->hasTo('alt@example.com') && ! $mail->hasTo('neu@example.com'));

    expect(ActivityLog::query()->where('action', 'user.email_changed')->sole()->changes)
        ->toBe([['field' => 'Login-E-Mail', 'old' => 'alt@example.com', 'new' => 'neu@example.com', 'masked' => false]]);

    // Anmeldung nur noch mit der neuen Adresse.
    $this->post('/logout');
    $this->post('/login', ['username' => 'alt@example.com', 'password' => 'geheimes-passwort'])->assertSessionHasErrors('username');
    $this->post('/login', ['username' => 'neu@example.com', 'password' => 'geheimes-passwort'])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($staff->fresh(), 'web');
});

it('falls back to the remaining member address when the login address is removed', function () {
    $staff = staffWithMember(['alt@example.com', 'zweite@example.com']);

    Livewire::test(MembersEdit::class, ['member' => $staff->member])
        ->call('removeEmail', 0)
        ->call('save')
        ->assertHasNoErrors();

    expect($staff->fresh()->email)->toBe('zweite@example.com');
    Mail::assertSent(StaffLoginEmailChangedMail::class, 1);
});

it('keeps the login address and warns when the member has no address left', function () {
    $staff = staffWithMember(['alt@example.com']);

    Livewire::test(MembersEdit::class, ['member' => $staff->member])
        ->call('removeEmail', 0)
        ->call('save')
        ->assertHasNoErrors();

    expect($staff->fresh()->email)->toBe('alt@example.com')
        ->and(session('warning'))->toContain('bleibt alt@example.com');

    Mail::assertNothingSent();
});

it('leaves the login address alone when another or an unrelated address changes', function () {
    $staff = staffWithMember(['alt@example.com', 'privat@example.com']);
    $other = staffWithMember(['mitglied@example.com'], loginEmail: 'eigene@example.com');

    Livewire::test(MembersEdit::class, ['member' => $staff->member])
        ->set('emails.1.email', 'privat-neu@example.com')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(MembersEdit::class, ['member' => $other->member])
        ->set('emails.0.email', 'mitglied-neu@example.com')
        ->call('save')
        ->assertHasNoErrors();

    expect($staff->fresh()->email)->toBe('alt@example.com')
        ->and($other->fresh()->email)->toBe('eigene@example.com');

    Mail::assertNothingSent();
});

it('lets admins change the login address in the user management', function () {
    $staff = staffWithMember(['alt@example.com']);

    Livewire::test(UsersIndex::class)
        ->call('editEmail', $staff->id)
        ->set('editedEmail', 'keine-adresse')
        ->call('saveEmail')
        ->assertHasErrors('editedEmail')
        ->set('editedEmail', 'ALT@example.com ')
        ->call('saveEmail')
        ->assertHasNoErrors();

    Mail::assertNothingSent();

    Livewire::test(UsersIndex::class)
        ->call('editEmail', $staff->id)
        ->set('editedEmail', 'vorstand@example.com')
        ->call('saveEmail')
        ->assertHasNoErrors()
        ->assertSee('vorstand@example.com')
        ->assertSee('nicht in den Mitgliedsdaten');

    expect($staff->fresh()->email)->toBe('vorstand@example.com');
    Mail::assertSent(StaffLoginEmailChangedMail::class, fn (StaffLoginEmailChangedMail $mail) => $mail->hasTo('alt@example.com'));
});
