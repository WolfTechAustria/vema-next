<?php

namespace App\Livewire\Admin\Settings;

use App\Enums\DemoResetMode;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\DemoDatabaseResetter;
use App\Services\DemoMode;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
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

    public string $whatsapp_community_url = '';

    public string $bank_name = '';

    public string $iban = '';

    public string $bic = '';

    public string $vat_id = '';

    public bool $small_business_default = true;

    public string $invoice_default_tax_rate = '20';

    public string $invoice_footer_text = '';

    public string $invoice_number_format = InvoiceNumberGenerator::DEFAULT_FORMAT;

    public string $invoice_reminder_first_after_days = '14';

    public string $invoice_reminder_interval_days = '14';

    public string $invoice_next_number = '1';

    /**
     * Beim Laden angezeigter Zählerstand — der Zähler wird nur überschrieben,
     * wenn er im Formular geändert wurde (sonst könnte ein zwischenzeitlich
     * vergebener Wert zurückgesetzt werden).
     */
    #[Locked]
    public int $loadedInvoiceNextNumber = 1;

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
        $this->whatsapp_community_url = $setting->whatsapp_community_url ?? '';

        $this->bank_name = $setting->bank_name ?? '';
        $this->iban = $setting->iban ?? '';
        $this->bic = $setting->bic ?? '';
        $this->vat_id = $setting->vat_id ?? '';
        $this->small_business_default = (bool) $setting->small_business_default;
        $this->invoice_default_tax_rate = number_format((float) ($setting->invoice_default_tax_rate ?? 20), 2, ',', '');
        $this->invoice_footer_text = $setting->invoice_footer_text ?? '';
        $this->invoice_reminder_first_after_days = (string) ($setting->invoice_reminder_first_after_days ?? 14);
        $this->invoice_reminder_interval_days = (string) ($setting->invoice_reminder_interval_days ?? 14);

        $numberGenerator = app(InvoiceNumberGenerator::class);
        $this->invoice_number_format = $numberGenerator->currentFormat();
        $this->loadedInvoiceNextNumber = $numberGenerator->nextNumberFor($this->invoice_number_format, (int) now()->year);
        $this->invoice_next_number = (string) $this->loadedInvoiceNextNumber;
    }

    /**
     * Beispiel der nächsten Rechnungsnummer für die Vorschau im Formular.
     */
    public function invoiceNumberPreview(): ?string
    {
        $numberGenerator = app(InvoiceNumberGenerator::class);

        if ($numberGenerator->validationError($this->invoice_number_format) !== null || ! ctype_digit($this->invoice_next_number)) {
            return null;
        }

        return $numberGenerator->format($this->invoice_number_format, (int) now()->year, (int) $this->invoice_next_number);
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
            'whatsapp_community_url' => ['nullable', 'url:https', 'max:255'],

            'bank_name' => ['nullable', 'string', 'max:150'],
            'iban' => ['nullable', 'string', 'max:50'],
            'bic' => ['nullable', 'string', 'max:20'],
            'vat_id' => ['nullable', 'string', 'max:50'],
            'small_business_default' => ['boolean'],
            'invoice_default_tax_rate' => ['required'],
            'invoice_footer_text' => ['nullable', 'string'],
            'invoice_number_format' => ['required', 'string', 'max:50'],
            'invoice_next_number' => ['required', 'integer', 'min:1', 'max:99999999'],
            'invoice_reminder_first_after_days' => ['required', 'integer', 'min:0', 'max:365'],
            'invoice_reminder_interval_days' => ['required', 'integer', 'min:1', 'max:365'],
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

        $numberGenerator = app(InvoiceNumberGenerator::class);
        $numberFormat = trim($validated['invoice_number_format']);
        $nextNumber = (int) $validated['invoice_next_number'];
        $currentYear = (int) now()->year;

        if ($formatError = $numberGenerator->validationError($numberFormat)) {
            $this->addError('invoice_number_format', $formatError);

            return;
        }

        if (mb_strlen($numberGenerator->format($numberFormat, $currentYear, $nextNumber)) > InvoiceNumberGenerator::MAX_LENGTH) {
            $this->addError('invoice_number_format', 'Rechnungsnummern dürfen höchstens '.InvoiceNumberGenerator::MAX_LENGTH.' Zeichen lang sein.');

            return;
        }

        $previousFormat = $numberGenerator->currentFormat();
        $before = [
            ...$this->settingsSnapshot(Setting::current()),
            'invoice_next_number' => $this->loadedInvoiceNextNumber,
        ];

        Setting::current()->update([
            'name' => $validated['name'],
            'street' => $validated['street'] ?: null,
            'zip' => $validated['zip'] ?: null,
            'city' => $validated['city'] ?: null,
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'website' => $validated['website'] ?: null,
            'whatsapp_community_url' => $validated['whatsapp_community_url'] ?: null,

            'bank_name' => $validated['bank_name'] ?: null,
            'iban' => $validated['iban'] ?: null,
            'bic' => $validated['bic'] ?: null,
            'vat_id' => $validated['vat_id'] ?: null,
            'small_business_default' => $validated['small_business_default'],
            'invoice_default_tax_rate' => round((float) $normalizedTaxRate, 2),
            'invoice_footer_text' => $validated['invoice_footer_text'] ?: null,
            'invoice_number_format' => $numberFormat,
            'invoice_reminder_first_after_days' => (int) $validated['invoice_reminder_first_after_days'],
            'invoice_reminder_interval_days' => (int) $validated['invoice_reminder_interval_days'],
        ]);

        // Zähler nur setzen, wenn er geändert wurde oder das Format auf einen
        // anderen Zähler (jährlich ↔ durchgehend) wechselt.
        $counterChanged = $numberGenerator->usesYearlyCounter($numberFormat) !== $numberGenerator->usesYearlyCounter($previousFormat);

        if ($nextNumber !== $this->loadedInvoiceNextNumber || $counterChanged) {
            $numberGenerator->setNextNumber($numberFormat, $currentYear, $nextNumber);
        }

        $this->invoice_number_format = $numberFormat;
        $this->loadedInvoiceNextNumber = $nextNumber;

        ActivityLogger::log(
            'settings.updated',
            'Vereinseinstellungen wurden geändert.',
            changes: ActivityLogger::changes(
                $before,
                [...$this->settingsSnapshot(Setting::current()->fresh()), 'invoice_next_number' => $nextNumber],
                self::ACTIVITY_LABELS,
                masked: ['iban', 'bic'],
            )
        );

        session()->flash('success', 'Einstellungen wurden gespeichert.');
    }

    /**
     * @var array<string, string>
     */
    private const ACTIVITY_LABELS = [
        'name' => 'Vereinsname',
        'street' => 'Straße',
        'zip' => 'PLZ',
        'city' => 'Ort',
        'email' => 'E-Mail',
        'phone' => 'Telefon',
        'website' => 'Website',
        'whatsapp_community_url' => 'WhatsApp-Community-Link',
        'bank_name' => 'Bank',
        'iban' => 'IBAN',
        'bic' => 'BIC',
        'vat_id' => 'UID-Nummer',
        'small_business_default' => 'Kleinunternehmer als Standard',
        'invoice_default_tax_rate' => 'Standard-USt.-Satz (%)',
        'invoice_footer_text' => 'Standard-Fußtext',
        'invoice_number_format' => 'Format der Rechnungsnummer',
        'invoice_next_number' => 'Nächste Rechnungsnummer',
        'invoice_reminder_first_after_days' => 'Tage bis zur Zahlungserinnerung',
        'invoice_reminder_interval_days' => 'Tage zwischen den Mahnstufen',
    ];

    /**
     * @return array<string, mixed>
     */
    private function settingsSnapshot(Setting $setting): array
    {
        return [
            ...$setting->only(array_keys(self::ACTIVITY_LABELS)),
            'small_business_default' => (bool) $setting->small_business_default,
            'invoice_default_tax_rate' => number_format((float) $setting->invoice_default_tax_rate, 2, ',', ''),
            'invoice_number_format' => $setting->invoice_number_format ?: InvoiceNumberGenerator::DEFAULT_FORMAT,
        ];
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

        $demoSettings = $demoMode->settings();
        $labels = ['demo_enabled' => 'Testmodus verfügbar', 'demo_reset_mode' => 'Zurücksetzen'];
        $before = $demoSettings->only(array_keys($labels));

        $demoSettings->update($validated);

        ActivityLogger::log(
            'settings.demo_updated',
            'Testmodus-Einstellungen wurden geändert.',
            changes: ActivityLogger::changes($before, $demoSettings->fresh()->only(array_keys($labels)), $labels)
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
