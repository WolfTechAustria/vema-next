<?php

namespace App\Livewire\Application;

use App\Enums\MemberFieldType;
use App\Models\City;
use App\Models\MemberField;
use App\Models\Setting;
use App\Services\MemberApplications;
use App\Services\MemberApplicationSettings;
use App\Services\MemberFields;
use App\Services\TurnstileVerifier;
use Carbon\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Öffentliches Formular der Online-Mitgliedsanmeldung (Beitrittserklärung).
 */
class Form extends Component
{
    /**
     * Anmeldungen je IP und Stunde.
     */
    public const MAX_SUBMISSIONS_PER_HOUR = 5;

    /**
     * Höchstgröße der Unterschrift (PNG) in Bytes.
     */
    private const MAX_SIGNATURE_BYTES = 512 * 1024;

    public string $gender = '';

    public string $name = '';

    public string $surname = '';

    public string $dateOfBirth = '';

    public string $street = '';

    public string $zip = '';

    public string $city = '';

    public string $email = '';

    public string $phone = '';

    /** Index der gewählten Mitgliedsart */
    public string $membershipType = '';

    /** @var array<int, string|bool> fieldID => Wert */
    public array $customFields = [];

    public string $guardianName = '';

    public string $place = '';

    /** @var array<int, bool> Index => angehakt */
    public array $consents = [];

    /** Unterschrift als PNG-Data-URL */
    public string $signature = '';

    public string $captchaToken = '';

    /**
     * Honeypot: bleibt für Menschen unsichtbar und leer.
     */
    public string $website = '';

    /** null | verify | done */
    public ?string $result = null;

    public function mount(MemberApplicationSettings $settings, MemberFields $memberFields): void
    {
        abort_unless($settings->isEnabled(), 404);

        $this->customFields = $memberFields->formValues(null, $settings->customFields());
        $this->consents = array_fill(0, count($settings->consents()), false);

        if (count($settings->membershipTypes()) === 1) {
            $this->membershipType = '0';
        }
    }

    public function updatedZip(string $value): void
    {
        $this->zip = trim($value);

        if ($this->city === '' && preg_match('/^\d{4}$/', $this->zip)) {
            $this->city = (string) City::query()->where('zip', $this->zip)->value('city');
        }
    }

    public function isMinor(): bool
    {
        if ($this->dateOfBirth === '') {
            return false;
        }

        try {
            return Carbon::parse($this->dateOfBirth)->diffInYears(today()) < 18;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        $settings = app(MemberApplicationSettings::class);
        $required = fn (string $field) => $settings->requires($field) ? 'required' : 'nullable';
        $types = $settings->membershipTypes();

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'surname' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc,filter', 'max:255'],
            ...app(MemberFields::class)->rules('customFields', $settings->customFields()),
        ];

        if ($settings->shows('gender')) {
            $rules['gender'] = [$required('gender'), Rule::in(['m', 'w'])];
        }

        if ($settings->shows('dateOfBirth')) {
            $rules['dateOfBirth'] = [$required('dateOfBirth'), 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'];
        }

        if ($settings->shows('address')) {
            $rules['street'] = [$required('address'), 'string', 'max:255'];
            $rules['zip'] = [$required('address'), 'regex:/^\d{4,5}$/'];
            $rules['city'] = [$required('address'), 'string', 'max:100'];
        }

        if ($settings->shows('phone')) {
            $rules['phone'] = [$required('phone'), 'string', 'max:100', 'regex:/^[0-9 +()\/-]+$/'];
        }

        if ($settings->shows('place')) {
            $rules['place'] = [$required('place'), 'string', 'max:100'];
        }

        if ($types !== []) {
            $rules['membershipType'] = ['required', Rule::in(array_map('strval', array_keys($types)))];
        }

        if ($this->isMinor()) {
            $rules['guardianName'] = ['required', 'string', 'max:150'];
        }

        foreach ($settings->consents() as $index => $consent) {
            $rules['consents.'.$index] = $consent['required'] ? ['accepted'] : ['boolean'];
        }

        if ($settings->requiresSignature()) {
            $rules['signature'] = ['required', 'string'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'required' => 'Bitte ausfüllen.',
            'email.email' => 'Bitte eine gültige E-Mail-Adresse eingeben (z. B. name@beispiel.at).',
            'zip.regex' => 'Bitte eine gültige Postleitzahl eingeben.',
            'phone.regex' => 'Bitte nur Ziffern, Leerzeichen und + / - verwenden.',
            'dateOfBirth.before' => 'Bitte ein gültiges Geburtsdatum eingeben.',
            'dateOfBirth.after' => 'Bitte ein gültiges Geburtsdatum eingeben.',
            'membershipType.required' => 'Bitte eine Mitgliedsart wählen.',
            'consents.*.accepted' => 'Bitte bestätigen.',
            'signature.required' => 'Bitte unterschreiben.',
            'guardianName.required' => 'Bei Minderjährigen bitte den Namen der/des Erziehungsberechtigten angeben.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return app(MemberFields::class)->attributes('customFields', app(MemberApplicationSettings::class)->customFields());
    }

    public function submit(MemberApplications $applications, MemberApplicationSettings $settings, TurnstileVerifier $turnstile): void
    {
        abort_unless($settings->isEnabled(), 404);

        // Bots füllen das versteckte Feld aus — so tun, als hätte alles geklappt.
        if ($this->website !== '') {
            $this->result = $settings->usesEmailVerification() ? 'verify' : 'done';

            return;
        }

        foreach (['name', 'surname', 'email', 'street', 'zip', 'city', 'phone', 'place', 'guardianName'] as $field) {
            $this->{$field} = trim($this->{$field});
        }

        $validated = $this->validate();
        $signaturePng = $settings->requiresSignature() ? $this->decodeSignature() : null;

        if ($settings->requiresSignature() && $signaturePng === null) {
            $this->addError('signature', 'Die Unterschrift konnte nicht gelesen werden. Bitte erneut unterschreiben.');

            return;
        }

        $throttleKey = 'member-application:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_SUBMISSIONS_PER_HOUR)) {
            $this->addError('form', 'Von diesem Anschluss wurden bereits mehrere Anmeldungen gesendet. Bitte versuche es später erneut.');

            return;
        }

        if ($settings->usesCaptcha() && ! $turnstile->verify($this->captchaToken, request()->ip())) {
            $this->captchaToken = '';
            $this->dispatch('captcha-reset');
            $this->addError('captcha', 'Die Sicherheitsprüfung ist fehlgeschlagen. Bitte erneut versuchen.');

            return;
        }

        RateLimiter::hit($throttleKey, 3600);

        $types = $settings->membershipTypes();
        $type = $types[(int) $this->membershipType] ?? null;
        $customFieldDefinitions = $settings->customFields();

        $applications->submit([
            'gender' => ($validated['gender'] ?? '') ?: null,
            'name' => $validated['name'],
            'surname' => $validated['surname'],
            'dateOfBirth' => ($validated['dateOfBirth'] ?? '') ?: null,
            'street' => ($validated['street'] ?? '') ?: null,
            'zip' => ($validated['zip'] ?? '') ?: null,
            'city' => ($validated['city'] ?? '') ?: null,
            'email' => $validated['email'],
            'phone' => ($validated['phone'] ?? '') ?: null,
            'membership_type' => $type['label'] ?? null,
            'membership_kind' => $type['kind'] ?? null,
            'guardian_name' => $this->isMinor() ? $this->guardianName : null,
            'place' => ($validated['place'] ?? '') ?: (($validated['city'] ?? '') ?: null),
            'custom_fields' => $customFieldDefinitions
                ->mapWithKeys(fn (MemberField $field) => [$field->fieldID => $field->type === MemberFieldType::Boolean
                    ? ((bool) ($this->customFields[$field->fieldID] ?? false) ? '1' : '0')
                    : trim((string) ($this->customFields[$field->fieldID] ?? ''))])
                ->filter(fn (string $value) => $value !== '' && $value !== '0')
                ->all(),
            'consents' => collect($settings->consents())
                ->map(fn (array $consent, int $index) => ['text' => $consent['text'], 'accepted' => (bool) ($this->consents[$index] ?? false)])
                ->all(),
        ], $signaturePng);

        $this->result = $settings->usesEmailVerification() ? 'verify' : 'done';
    }

    /**
     * PNG-Daten der Unterschrift oder null, wenn sie ungültig ist.
     */
    private function decodeSignature(): ?string
    {
        if (! str_starts_with($this->signature, 'data:image/png;base64,')) {
            return null;
        }

        $png = base64_decode(substr($this->signature, strlen('data:image/png;base64,')), true);

        if ($png === false || strlen($png) > self::MAX_SIGNATURE_BYTES) {
            return null;
        }

        $info = @getimagesizefromstring($png);

        return $info !== false && $info[2] === IMAGETYPE_PNG ? $png : null;
    }

    public function render(MemberApplicationSettings $settings, TurnstileVerifier $turnstile)
    {
        $clubName = Setting::current()->name;

        return view('livewire.application.form', [
            'settings' => $settings,
            'clubName' => $clubName,
            'membershipTypes' => $settings->membershipTypes(),
            'consentTexts' => $settings->consents(),
            'customFieldDefinitions' => $settings->customFields(),
            'captchaSiteKey' => $settings->usesCaptcha() ? $turnstile->siteKey() : null,
            'today' => now(config('app.club_timezone')),
        ])->layout('layouts.member-auth', [
            'title' => 'Mitglied werden | '.$clubName,
        ]);
    }
}
