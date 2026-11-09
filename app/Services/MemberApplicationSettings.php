<?php

namespace App\Services;

use App\Enums\MembershipKind;
use App\Models\MemberField;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;

/**
 * Konfiguration der Online-Mitgliedsanmeldung aus den Vereinseinstellungen,
 * ergänzt um sinnvolle Standardwerte.
 */
class MemberApplicationSettings
{
    /**
     * Standardfelder, die ein- und ausgeblendet bzw. verpflichtend sein
     * können. Vorname, Nachname und E-Mail sind immer Pflicht.
     *
     * @var array<string, string>
     */
    public const FIELDS = [
        'gender' => 'Anrede',
        'dateOfBirth' => 'Geburtsdatum',
        'address' => 'Adresse (Straße, PLZ, Ort)',
        'phone' => 'Telefon',
        'place' => 'Ort der Unterzeichnung',
    ];

    public const SPAM_EMAIL = 'email';

    public const SPAM_CAPTCHA = 'captcha';

    public const SPAM_BOTH = 'both';

    /**
     * @var array<string, string>
     */
    public const SPAM_OPTIONS = [
        self::SPAM_EMAIL => 'E-Mail-Bestätigung',
        self::SPAM_CAPTCHA => 'Captcha',
        self::SPAM_BOTH => 'Captcha und E-Mail-Bestätigung',
    ];

    public function __construct(
        private TurnstileVerifier $turnstile,
        private MemberFields $memberFields,
    ) {}

    public function setting(): Setting
    {
        return Setting::current();
    }

    public function isEnabled(): bool
    {
        return (bool) $this->setting()->application_enabled;
    }

    public function intro(): ?string
    {
        return $this->setting()->application_intro;
    }

    public function privacyText(): ?string
    {
        return $this->setting()->application_privacy_text;
    }

    /**
     * Sichtbarkeit und Pflicht der Standardfelder (ohne Einstellung: alles
     * sichtbar, alles außer Telefon verpflichtend).
     *
     * @return array<string, array{visible: bool, required: bool}>
     */
    public function fields(): array
    {
        $stored = $this->setting()->application_fields ?? [];

        return collect(self::FIELDS)
            ->mapWithKeys(function (string $label, string $key) use ($stored) {
                $visible = (bool) ($stored[$key]['visible'] ?? true);

                return [$key => [
                    'visible' => $visible,
                    'required' => $visible && (bool) ($stored[$key]['required'] ?? $key !== 'phone'),
                ]];
            })
            ->all();
    }

    public function shows(string $field): bool
    {
        return $this->fields()[$field]['visible'] ?? false;
    }

    public function requires(string $field): bool
    {
        return $this->fields()[$field]['required'] ?? false;
    }

    /**
     * @return list<array{label: string, info: string, kind: string}>
     */
    public function membershipTypes(): array
    {
        $types = $this->setting()->application_membership_types;

        if ($types === null) {
            return self::defaultMembershipTypes();
        }

        return collect($types)
            ->map(fn (array $type) => [
                'label' => trim((string) ($type['label'] ?? '')),
                'info' => trim((string) ($type['info'] ?? '')),
                'kind' => MembershipKind::tryFrom((string) ($type['kind'] ?? ''))?->value ?? MembershipKind::None->value,
            ])
            ->filter(fn (array $type) => $type['label'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array{text: string, required: bool}>
     */
    public function consents(): array
    {
        $consents = $this->setting()->application_consents;

        if ($consents === null) {
            return self::defaultConsents();
        }

        return collect($consents)
            ->map(fn (array $consent) => [
                'text' => trim((string) ($consent['text'] ?? '')),
                'required' => (bool) ($consent['required'] ?? false),
            ])
            ->filter(fn (array $consent) => $consent['text'] !== '')
            ->values()
            ->all();
    }

    /**
     * Gewählter Spam-Schutz; ohne Turnstile-Schlüssel gilt immer die
     * E-Mail-Bestätigung.
     */
    public function spamProtection(): string
    {
        $mode = (string) $this->setting()->application_spam_protection;

        if (! array_key_exists($mode, self::SPAM_OPTIONS) || ! $this->turnstile->isConfigured()) {
            return self::SPAM_EMAIL;
        }

        return $mode;
    }

    public function usesCaptcha(): bool
    {
        return in_array($this->spamProtection(), [self::SPAM_CAPTCHA, self::SPAM_BOTH], true);
    }

    public function usesEmailVerification(): bool
    {
        return in_array($this->spamProtection(), [self::SPAM_EMAIL, self::SPAM_BOTH], true);
    }

    public function requiresSignature(): bool
    {
        return (bool) $this->setting()->application_signature;
    }

    /**
     * @return list<string>
     */
    public function notifyEmails(): array
    {
        return array_values(array_filter($this->setting()->application_notify_emails ?? []));
    }

    /**
     * Eigene Mitgliederfelder, die im Formular abgefragt werden.
     *
     * @return Collection<int, MemberField>
     */
    public function customFields(): Collection
    {
        return $this->memberFields->fields()
            ->filter(fn (MemberField $field) => $field->on_application)
            ->values();
    }

    /**
     * @return list<array{label: string, info: string, kind: string}>
     */
    public static function defaultMembershipTypes(): array
    {
        return [
            ['label' => 'Ordentliches Mitglied', 'info' => '', 'kind' => MembershipKind::Ordinary->value],
            ['label' => 'Unterstützendes Mitglied', 'info' => '', 'kind' => MembershipKind::Supporting->value],
        ];
    }

    /**
     * @return list<array{text: string, required: bool}>
     */
    public static function defaultConsents(): array
    {
        return [
            ['text' => 'Ich habe die Datenschutzhinweise gelesen und stimme der Verarbeitung meiner Daten durch den Verein zu.', 'required' => true],
        ];
    }
}
