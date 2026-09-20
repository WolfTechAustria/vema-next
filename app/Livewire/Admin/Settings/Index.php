<?php

namespace App\Livewire\Admin\Settings;

use App\Models\Setting;
use Livewire\Component;

class Index extends Component
{
    public string $name = '';
    public string $street = '';
    public string $zip = '';
    public string $city = '';
    public string $email = '';
    public string $phone = '';
    public string $website = '';

    public function mount(): void
    {
        $setting = Setting::current();

        $this->name = $setting->name ?? '';
        $this->street = $setting->street ?? '';
        $this->zip = $setting->zip ?? '';
        $this->city = $setting->city ?? '';
        $this->email = $setting->email ?? '';
        $this->phone = $setting->phone ?? '';
        $this->website = $setting->website ?? '';
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'street' => ['nullable', 'string', 'max:150'],
            'zip' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        Setting::current()->update([
            'name' => $validated['name'],
            'street' => $validated['street'] ?: null,
            'zip' => $validated['zip'] ?: null,
            'city' => $validated['city'] ?: null,
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'website' => $validated['website'] ?: null,
        ]);

        session()->flash('success', 'Einstellungen wurden gespeichert.');
    }

    public function render()
    {
        return view('livewire.admin.settings.index')
            ->layout('layouts.app', [
                'title' => 'Einstellungen | VEMA',
                'heading' => 'Einstellungen',
            ]);
    }
}
