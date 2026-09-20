<?php

namespace App\Livewire\Admin\Users;

use App\Mail\StaffPasswordResetMail;
use App\Models\Member;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Component;

class Index extends Component
{
    public string $memberSearch = '';

    public bool $showInviteForm = false;

    public ?int $selectedMemberId = null;

    public string $newUsername = '';

    public string $newEmail = '';

    public bool $grantAdmin = false;

    protected function rules(): array
    {
        return [
            'selectedMemberId' => ['required', 'integer', 'exists:tb_members,memberID'],
            'newUsername' => ['required', 'string', 'max:100', 'unique:tb_user,username'],
            'newEmail' => ['required', 'email', 'max:100'],
            'grantAdmin' => ['boolean'],
        ];
    }

    public function selectMember(int $memberId): void
    {
        $member = Member::findOrFail($memberId);

        $this->selectedMemberId = $member->memberID;
        $this->newUsername = Str::slug($member->name . '.' . $member->surname, '');
        $this->newEmail = $member->emails->first()?->email ?? '';
        $this->showInviteForm = true;
        $this->memberSearch = '';

        $this->resetValidation();
    }

    public function cancelInvite(): void
    {
        $this->reset(['selectedMemberId', 'newUsername', 'newEmail', 'grantAdmin', 'showInviteForm']);
        $this->resetValidation();
    }

    public function createUser(): void
    {
        $validated = $this->validate();

        $user = User::create([
            'username' => $validated['newUsername'],
            'email' => $validated['newEmail'],
            // Unbenutzbares Zufallspasswort — der neue Benutzer legt sein
            // eigenes Passwort über den zugeschickten Link fest.
            'password' => Hash::make(Str::random(40)),
            'enabled' => true,
            'function' => 0,
            'companyname' => '',
            'companyname_short' => '',
            'registerDate' => now(),
            'memberID' => $validated['selectedMemberId'],
        ]);

        if ($validated['grantAdmin']) {
            $user->assignRole('admin');
        }

        $token = Password::getRepository()->create($user);

        Mail::to($user->email)->send(
            new StaffPasswordResetMail($user, $token, isNewAccount: true)
        );

        ActivityLogger::log(
            'user.created',
            'Benutzer "' . $user->username . '" wurde angelegt' . ($validated['grantAdmin'] ? ' (mit Admin-Rechten)' : '') . '.',
            'User',
            $user->id
        );

        $this->cancelInvite();

        session()->flash(
            'success',
            'Benutzer "' . $user->username . '" wurde angelegt. Eine E-Mail zum Festlegen des Passworts wurde verschickt.'
        );
    }

    public function toggleEnabled(int $userId): void
    {
        $user = User::findOrFail($userId);

        if ($user->id === auth()->id() && $user->enabled) {
            session()->flash('error', 'Du kannst dich nicht selbst sperren.');

            return;
        }

        $user->update(['enabled' => !$user->enabled]);

        ActivityLogger::log(
            $user->enabled ? 'user.enabled' : 'user.disabled',
            'Benutzer "' . $user->username . '" wurde ' . ($user->enabled ? 'entsperrt' : 'gesperrt') . '.',
            'User',
            $user->id
        );
    }

    public function toggleAdmin(int $userId): void
    {
        $user = User::findOrFail($userId);

        if ($user->hasRole('admin')) {
            $remainingAdmins = User::role('admin')->where('id', '!=', $userId)->count();

            if ($remainingAdmins === 0) {
                session()->flash(
                    'error',
                    'Diesem Benutzer kann die Admin-Rolle nicht entzogen werden, da er der letzte verbleibende Admin ist.'
                );

                return;
            }

            $user->removeRole('admin');

            ActivityLogger::log(
                'user.admin_revoked',
                'Benutzer "' . $user->username . '" wurden die Admin-Rechte entzogen.',
                'User',
                $user->id
            );
        } else {
            $user->assignRole('admin');

            ActivityLogger::log(
                'user.admin_granted',
                'Benutzer "' . $user->username . '" wurden Admin-Rechte vergeben.',
                'User',
                $user->id
            );
        }
    }

    public function resendInvite(int $userId): void
    {
        $user = User::findOrFail($userId);

        $token = Password::getRepository()->create($user);

        Mail::to($user->email)->send(
            new StaffPasswordResetMail($user, $token, isNewAccount: false)
        );

        session()->flash('success', 'Link zum Zurücksetzen des Passworts wurde erneut an ' . $user->email . ' verschickt.');
    }

    public function render()
    {
        $users = User::with(['member', 'roles'])
            ->orderBy('username')
            ->get();

        $existingMemberIds = User::pluck('memberID');

        $availableMembers = collect();

        if ($this->memberSearch !== '') {
            $availableMembers = Member::query()
                ->where('active', 1)
                ->whereNotIn('memberID', $existingMemberIds)
                ->where(function ($query) {
                    $query
                        ->where('name', 'like', '%' . $this->memberSearch . '%')
                        ->orWhere('surname', 'like', '%' . $this->memberSearch . '%');
                })
                ->orderBy('surname')
                ->orderBy('name')
                ->limit(10)
                ->get();
        }

        return view('livewire.admin.users.index', [
            'users' => $users,
            'availableMembers' => $availableMembers,
        ])->layout('layouts.app', [
            'title' => 'Benutzerverwaltung | VEMA',
            'heading' => 'Benutzerverwaltung',
        ]);
    }
}
