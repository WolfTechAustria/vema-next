<?php

namespace App\Livewire\Central\Admin;

use App\Models\PlatformAdmin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Eigenes Konto des Plattform-Admins: Name, E-Mail, Passwort; dazu die
 * Liste aller Plattform-Admins.
 */
class Account extends Component
{
    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $admin = $this->admin();

        $this->name = $admin->name;
        $this->email = $admin->email;
    }

    public function saveProfile(): void
    {
        $admin = $this->admin();

        $this->email = mb_strtolower(trim($this->email));

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique(PlatformAdmin::class, 'email')->ignore($admin->getKey())],
        ], [
            'name.required' => 'Bitte einen Namen eingeben.',
            'email.required' => 'Bitte eine E-Mail-Adresse eingeben.',
            'email.email' => 'Bitte eine gültige E-Mail-Adresse eingeben.',
            'email.unique' => 'Diese E-Mail-Adresse wird bereits verwendet.',
        ]);

        $admin->update($validated);

        session()->flash('success', 'Konto gespeichert.');
    }

    public function changePassword(): void
    {
        $admin = $this->admin();

        $this->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ], [
            'current_password.required' => 'Bitte das aktuelle Passwort eingeben.',
            'password.required' => 'Bitte ein neues Passwort eingeben.',
            'password.min' => 'Das Passwort muss mindestens 12 Zeichen haben.',
            'password.confirmed' => 'Die Passwörter stimmen nicht überein.',
        ]);

        if (! Hash::check($this->current_password, $admin->password)) {
            $this->addError('current_password', 'Das aktuelle Passwort ist falsch.');

            return;
        }

        $admin->update(['password' => $this->password]);

        $this->reset('current_password', 'password', 'password_confirmation');

        session()->flash('success', 'Passwort geändert.');
    }

    private function admin(): PlatformAdmin
    {
        return auth('platform')->user();
    }

    public function render()
    {
        return view('livewire.central.admin.account', [
            'admins' => PlatformAdmin::query()->orderBy('name')->get(),
        ])->layout('layouts.platform-admin', [
            'title' => 'Konto | Plattform-Admin',
        ]);
    }
}
