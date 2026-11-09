<?php

namespace App\Livewire\Admin\Settings;

use App\Enums\MembershipKind;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\HomepageHosts;
use App\Services\MemberApplicationSettings;
use App\Services\MemberFields;
use App\Services\TurnstileVerifier;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Online-Mitgliedsanmeldung einrichten: Texte, Felder, Mitgliedsarten,
 * Zustimmungen, Spam-Schutz und Benachrichtigung.
 */
class Application extends Component
{
    /**
     * @var array<string, string>
     */
    private const ACTIVITY_LABELS = [
        'enabled' => 'Aktiv',
        'intro' => 'Einleitung',
        'privacy_text' => 'Datenschutz',
        'spam_protection' => 'Spam-Schutz',
        'signature' => 'Unterschrift',
        'notify_emails' => 'Benachrichtigung an',
        'fields' => 'Felder',
        'membership_types' => 'Mitgliedsarten',
        'consents' => 'Zustimmungen',
    ];

    public bool $enabled = false;

    public string $intro = '';

    public string $privacyText = '';

    public string $spamProtection = MemberApplicationSettings::SPAM_EMAIL;

    public bool $signature = true;

    /** Empfänger, einer pro Zeile */
    public string $notifyEmails = '';

    /** @var array<string, array{visible: bool, required: bool}> */
    public array $fields = [];

    /** @var list<array{label: string, info: string, kind: string}> */
    public array $membershipTypes = [];

    /** @var list<array{text: string, required: bool}> */
    public array $consents = [];

    public function mount(MemberApplicationSettings $settings): void
    {
        $setting = $settings->setting();

        $this->enabled = (bool) $setting->application_enabled;
        $this->intro = (string) $setting->application_intro;
        $this->privacyText = (string) $setting->application_privacy_text;
        $this->spamProtection = (string) ($setting->application_spam_protection ?: MemberApplicationSettings::SPAM_EMAIL);
        $this->signature = (bool) $setting->application_signature;
        $this->notifyEmails = implode("\n", $settings->notifyEmails());
        $this->fields = $settings->fields();
        $this->membershipTypes = $settings->membershipTypes();
        $this->consents = $settings->consents();
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'enabled' => ['boolean'],
            'intro' => ['nullable', 'string', 'max:5000'],
            'privacyText' => ['nullable', 'string', 'max:20000'],
            'spamProtection' => ['required', Rule::in(array_keys(MemberApplicationSettings::SPAM_OPTIONS))],
            'signature' => ['boolean'],
            'notifyEmails' => ['nullable', 'string', 'max:2000'],
            'fields' => ['array'],
            'fields.*.visible' => ['boolean'],
            'fields.*.required' => ['boolean'],
            'membershipTypes' => ['array', 'max:10'],
            'membershipTypes.*.label' => ['required', 'string', 'max:150'],
            'membershipTypes.*.info' => ['nullable', 'string', 'max:2000'],
            'membershipTypes.*.kind' => ['required', Rule::enum(MembershipKind::class)],
            'consents' => ['array', 'max:10'],
            'consents.*.text' => ['required', 'string', 'max:1000'],
            'consents.*.required' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'membershipTypes.*.label.required' => 'Bitte eine Bezeichnung eingeben.',
            'consents.*.text.required' => 'Bitte einen Text eingeben oder die Zustimmung entfernen.',
        ];
    }

    public function addMembershipType(): void
    {
        $this->membershipTypes[] = ['label' => '', 'info' => '', 'kind' => MembershipKind::Ordinary->value];
    }

    public function removeMembershipType(int $index): void
    {
        unset($this->membershipTypes[$index]);
        $this->membershipTypes = array_values($this->membershipTypes);
    }

    public function moveMembershipType(int $index, int $direction): void
    {
        $target = $index + $direction;

        if (! isset($this->membershipTypes[$index], $this->membershipTypes[$target])) {
            return;
        }

        [$this->membershipTypes[$index], $this->membershipTypes[$target]] = [$this->membershipTypes[$target], $this->membershipTypes[$index]];
    }

    public function addConsent(): void
    {
        $this->consents[] = ['text' => '', 'required' => false];
    }

    public function removeConsent(int $index): void
    {
        unset($this->consents[$index]);
        $this->consents = array_values($this->consents);
    }

    public function save(MemberApplicationSettings $settings): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $this->validate();

        $emails = collect(preg_split('/[\s,;]+/', $this->notifyEmails))
            ->map(fn (string $email) => trim($email))
            ->filter()
            ->unique()
            ->values();

        $invalid = $emails->reject(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL));

        if ($invalid->isNotEmpty()) {
            $this->addError('notifyEmails', 'Ungültige E-Mail-Adresse: '.$invalid->implode(', '));

            return;
        }

        $setting = $settings->setting();
        $before = $this->snapshot($setting);

        $setting->update([
            'application_enabled' => $this->enabled,
            'application_intro' => trim($this->intro) ?: null,
            'application_privacy_text' => trim($this->privacyText) ?: null,
            'application_spam_protection' => $this->spamProtection,
            'application_signature' => $this->signature,
            'application_notify_emails' => $emails->all(),
            'application_fields' => collect(MemberApplicationSettings::FIELDS)
                ->mapWithKeys(fn (string $label, string $key) => [$key => [
                    'visible' => (bool) ($this->fields[$key]['visible'] ?? false),
                    'required' => (bool) ($this->fields[$key]['visible'] ?? false) && (bool) ($this->fields[$key]['required'] ?? false),
                ]])
                ->all(),
            'application_membership_types' => collect($this->membershipTypes)
                ->map(fn (array $type) => ['label' => trim($type['label']), 'info' => trim((string) $type['info']), 'kind' => $type['kind']])
                ->values()
                ->all(),
            'application_consents' => collect($this->consents)
                ->map(fn (array $consent) => ['text' => trim($consent['text']), 'required' => (bool) $consent['required']])
                ->values()
                ->all(),
        ]);

        $changes = ActivityLogger::changes($before, $this->snapshot($setting->refresh()), self::ACTIVITY_LABELS);

        if ($changes !== []) {
            ActivityLogger::log('settings.application_updated', 'Einstellungen der Online-Anmeldung wurden geändert.', 'Setting', $setting->settingsID, $changes);
        }

        $this->mount($settings);

        session()->flash('success', 'Einstellungen wurden gespeichert.');
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Setting $setting): array
    {
        return [
            'enabled' => (bool) $setting->application_enabled,
            'intro' => $setting->application_intro,
            'privacy_text' => $setting->application_privacy_text,
            'spam_protection' => MemberApplicationSettings::SPAM_OPTIONS[$setting->application_spam_protection] ?? $setting->application_spam_protection,
            'signature' => (bool) $setting->application_signature,
            'notify_emails' => $setting->application_notify_emails ?? [],
            'fields' => collect($setting->application_fields ?? [])
                ->filter(fn (array $field) => $field['visible'] ?? false)
                ->map(fn (array $field, string $key) => (MemberApplicationSettings::FIELDS[$key] ?? $key).(($field['required'] ?? false) ? ' (Pflicht)' : ''))
                ->values()
                ->all(),
            'membership_types' => collect($setting->application_membership_types ?? [])->pluck('label')->all(),
            'consents' => collect($setting->application_consents ?? [])->pluck('text')->all(),
        ];
    }

    public function render(TurnstileVerifier $turnstile, MemberFields $memberFields, HomepageHosts $hosts)
    {
        return view('livewire.admin.settings.application', [
            'fieldLabels' => MemberApplicationSettings::FIELDS,
            'spamOptions' => MemberApplicationSettings::SPAM_OPTIONS,
            'kinds' => MembershipKind::cases(),
            'captchaAvailable' => $turnstile->isConfigured(),
            'memberFieldsEnabled' => $memberFields->isEnabled(),
            'applicationFields' => $memberFields->fields()->filter->on_application->values(),
            'formUrl' => $hosts->applicationUrl(),
        ])->layout('layouts.app', [
            'title' => 'Online-Anmeldung | VEMA',
            'heading' => 'Einstellungen',
        ]);
    }
}
