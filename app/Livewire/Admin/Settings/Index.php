<?php

namespace App\Livewire\Admin\Settings;

use App\Enums\DemoResetMode;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\DemoDatabaseResetter;
use App\Services\DemoMode;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Throwable;

class Index extends Component
{
    public string $name = '';

    public string $street = '';

    public string $zip = '';

    public string $city = '';

    public string $email = '';

    public string $phone = '';

    public string $website = '';

    public string $bank_name = '';

    public string $iban = '';

    public string $bic = '';

    public string $vat_id = '';

    public bool $small_business_default = true;

    public string $invoice_default_tax_rate = '20';

    public string $invoice_footer_text = '';

    public bool $demo_enabled = false;

    public string $demo_reset_mode = 'nightly';

    public function mount(DemoMode $demoMode): void
    {
        $setting = Setting::current();

        $demoSettings = $demoMode->settings();
        $this->demo_enabled = (bool) $demoSettings->demo_enabled;
        $this->demo_reset_mode = ($demoSettings->demo_reset_mode ?? DemoResetMode::Nightly)->value;

        $this->name = $setting->name ?? '';
        $this->street = $setting->street ?? '';
        $this->zip = $setting->zip ?? '';
        $this->city = $setting->city ?? '';
        $this->email = $setting->email ?? '';
        $this->phone = $setting->phone ?? '';
        $this->website = $setting->website ?? '';

        $this->bank_name = $setting->bank_name ?? '';
        $this->iban = $setting->iban ?? '';
        $this->bic = $setting->bic ?? '';
        $this->vat_id = $setting->vat_id ?? '';
        $this->small_business_default = (bool) $setting->small_business_default;
        $this->invoice_default_tax_rate = number_format((float) ($setting->invoice_default_tax_rate ?? 20), 2, ',', '');
        $this->invoice_footer_text = $setting->invoice_footer_text ?? '';
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

            'bank_name' => ['nullable', 'string', 'max:150'],
            'iban' => ['nullable', 'string', 'max:50'],
            'bic' => ['nullable', 'string', 'max:20'],
            'vat_id' => ['nullable', 'string', 'max:50'],
            'small_business_default' => ['boolean'],
            'invoice_default_tax_rate' => ['required'],
            'invoice_footer_text' => ['nullable', 'string'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $normalizedTaxRate = str_replace(',', '.', (string) $validated['invoice_default_tax_rate']);

        if (! is_numeric($normalizedTaxRate)) {
            $this->addError('invoice_default_tax_rate', 'Bitte einen gültigen USt.-Satz eingeben.');

            return;
        }

        Setting::current()->update([
            'name' => $validated['name'],
            'street' => $validated['street'] ?: null,
            'zip' => $validated['zip'] ?: null,
            'city' => $validated['city'] ?: null,
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'website' => $validated['website'] ?: null,

            'bank_name' => $validated['bank_name'] ?: null,
            'iban' => $validated['iban'] ?: null,
            'bic' => $validated['bic'] ?: null,
            'vat_id' => $validated['vat_id'] ?: null,
            'small_business_default' => $validated['small_business_default'],
            'invoice_default_tax_rate' => round((float) $normalizedTaxRate, 2),
            'invoice_footer_text' => $validated['invoice_footer_text'] ?: null,
        ]);

        ActivityLogger::log(
            'settings.updated',
            'Vereinseinstellungen wurden geändert.'
        );

        session()->flash('success', 'Einstellungen wurden gespeichert.');
    }

    /**
     * Die Testmodus-Einstellungen liegen immer in der Live-DB und sind aus
     * dem Testmodus heraus nicht änderbar.
     */
    public function saveDemoSettings(DemoMode $demoMode): void
    {
        if ($demoMode->isActive()) {
            return;
        }

        $validated = $this->validate([
            'demo_enabled' => ['boolean'],
            'demo_reset_mode' => ['required', Rule::enum(DemoResetMode::class)],
        ]);

        $demoMode->settings()->update($validated);

        ActivityLogger::log(
            'settings.demo_updated',
            'Testmodus-Einstellungen wurden geändert.'
        );

        session()->flash('success', 'Testmodus-Einstellungen wurden gespeichert.');
    }

    public function resetDemoData(DemoMode $demoMode, DemoDatabaseResetter $resetter): void
    {
        if ($demoMode->isActive()) {
            return;
        }

        try {
            $resetter->reset();
        } catch (Throwable $exception) {
            report($exception);
            session()->flash('error', 'Zurücksetzen fehlgeschlagen: '.$exception->getMessage());

            return;
        }

        ActivityLogger::log(
            'settings.demo_reset',
            'Testdaten wurden auf den Live-Stand zurückgesetzt.'
        );

        session()->flash('success', 'Testdaten wurden auf den Live-Stand zurückgesetzt.');
    }

    public function render(DemoMode $demoMode)
    {
        return view('livewire.admin.settings.index', [
            'isDemoActive' => $demoMode->isActive(),
            'demoLastResetAt' => $demoMode->settings()->demo_last_reset_at,
            'demoResetModes' => DemoResetMode::cases(),
        ])
            ->layout('layouts.app', [
                'title' => 'Einstellungen | VEMA',
                'heading' => 'Einstellungen',
            ]);
    }
}
