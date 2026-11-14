<?php

namespace App\Livewire\Admin\Settings;

use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\Sepa\SepaValidator;
use App\Services\SepaDebitRuns;
use App\Services\SepaMandates;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Einstellungen zur SEPA-Lastschrift: Gläubiger-ID, Kassabuch-Buchung,
 * Vorlauf für die Vorabinformation und Mandatstext.
 */
class Sepa extends Component
{
    /**
     * @var array<string, string>
     */
    public const CASH_BOOK_MODES = [
        SepaDebitRuns::CASH_BOOK_SUMMARY => 'Eine Sammelbuchung je Einzug',
        SepaDebitRuns::CASH_BOOK_PER_MEMBER => 'Eine Buchung je Mitglied',
        SepaDebitRuns::CASH_BOOK_NONE => 'Nicht ins Kassabuch buchen',
    ];

    private const LABELS = [
        'sepa_creditor_id' => 'Gläubiger-ID',
        'sepa_cash_book_mode' => 'Kassabuch',
        'sepa_lead_days' => 'Vorlauf (Bankarbeitstage)',
        'sepa_mandate_text' => 'Mandatstext',
    ];

    public string $creditorId = '';

    public string $cashBookMode = SepaDebitRuns::CASH_BOOK_SUMMARY;

    public int $leadDays = 5;

    public string $mandateText = '';

    public function mount(SepaDebitRuns $runs): void
    {
        $setting = Setting::current();

        $this->creditorId = (string) $setting->sepa_creditor_id;
        $this->cashBookMode = $runs->cashBookMode();
        $this->leadDays = (int) ($setting->sepa_lead_days ?? 5);
        $this->mandateText = (string) $setting->sepa_mandate_text;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $this->creditorId = SepaValidator::normalize($this->creditorId);

        $validated = $this->validate([
            'creditorId' => ['nullable', 'string', 'max:35', function (string $attribute, mixed $value, \Closure $fail) {
                if (filled($value) && ! app(SepaValidator::class)->isValidCreditorId($value)) {
                    $fail('Die Gläubiger-ID ist ungültig (Format z. B. AT61ZZZ01234567890).');
                }
            }],
            'cashBookMode' => ['required', Rule::in(array_keys(self::CASH_BOOK_MODES))],
            'leadDays' => ['required', 'integer', 'min:1', 'max:30'],
            'mandateText' => ['nullable', 'string', 'max:2000'],
        ], [], ['leadDays' => 'Vorlauf']);

        $setting = Setting::current();
        $before = $setting->only(array_keys(self::LABELS));

        $setting->update([
            'sepa_creditor_id' => $validated['creditorId'] ?: null,
            'sepa_cash_book_mode' => $validated['cashBookMode'],
            'sepa_lead_days' => $validated['leadDays'],
            'sepa_mandate_text' => trim((string) $validated['mandateText']) ?: null,
        ]);

        ActivityLogger::log(
            'settings.sepa_updated',
            'Einstellungen zur SEPA-Lastschrift wurden geändert.',
            'Setting',
            $setting->getKey(),
            ActivityLogger::changes($before, $setting->fresh()->only(array_keys(self::LABELS)), self::LABELS)
        );

        session()->flash('success', 'Einstellungen gespeichert.');
    }

    public function render(SepaMandates $mandates): View
    {
        return view('livewire.admin.settings.sepa', [
            'cashBookModes' => self::CASH_BOOK_MODES,
            'clubIban' => Setting::current()->iban,
            'previewText' => $mandates->consentText(),
        ])->layout('layouts.app', [
            'title' => 'SEPA-Lastschrift | VEMA',
            'heading' => 'Einstellungen',
        ]);
    }
}
