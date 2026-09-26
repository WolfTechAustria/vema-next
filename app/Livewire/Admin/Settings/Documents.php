<?php

namespace App\Livewire\Admin\Settings;

use App\Models\RecipientGroup;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\ClubBranding;
use App\Services\DemoMode;
use App\Services\ImapSentMailService;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

class Documents extends Component
{
    use WithFileUploads;

    /**
     * Upload-Property → Branding-Slot.
     *
     * @var array<string, string>
     */
    private const UPLOAD_SLOTS = [
        'logoUpload' => 'logo',
        'letterheadUpload' => 'letterhead',
        'signature1Upload' => 'signature_1',
        'signature2Upload' => 'signature_2',
    ];

    public string $letter_place = '';

    public string $signatory_1_title = '';

    public string $signatory_1_name = '';

    public string $signatory_2_title = '';

    public string $signatory_2_name = '';

    public ?TemporaryUploadedFile $logoUpload = null;

    public ?TemporaryUploadedFile $letterheadUpload = null;

    public ?TemporaryUploadedFile $signature1Upload = null;

    public ?TemporaryUploadedFile $signature2Upload = null;

    public string $mail_from_address = '';

    public string $mail_from_name = '';

    public string $birthday_recipient_group = '';

    public string $imap_host = '';

    public string $imap_port = '';

    public string $imap_encryption = 'ssl';

    public bool $imap_validate_cert = true;

    public string $imap_username = '';

    /**
     * Wird nie vorbefüllt; leer lassen = bisheriges Passwort behalten.
     */
    public string $imap_password = '';

    public string $imap_sent_folder = '';

    public function mount(): void
    {
        $setting = Setting::current();

        $this->letter_place = $setting->letter_place ?? '';
        $this->signatory_1_title = $setting->signatory_1_title ?? '';
        $this->signatory_1_name = $setting->signatory_1_name ?? '';
        $this->signatory_2_title = $setting->signatory_2_title ?? '';
        $this->signatory_2_name = $setting->signatory_2_name ?? '';

        $this->mail_from_address = $setting->mail_from_address ?? '';
        $this->mail_from_name = $setting->mail_from_name ?? '';
        $this->birthday_recipient_group = $setting->birthday_recipient_group ?? '';

        $this->imap_host = $setting->imap_host ?? '';
        $this->imap_port = $setting->imap_port ? (string) $setting->imap_port : '';
        $this->imap_encryption = $setting->imap_encryption ?? 'ssl';
        $this->imap_validate_cert = (bool) ($setting->imap_validate_cert ?? true);
        $this->imap_username = $setting->imap_username ?? '';
        $this->imap_sent_folder = $setting->imap_sent_folder ?? '';
    }

    public function saveLetter(): void
    {
        $validated = $this->validate([
            'letter_place' => ['nullable', 'string', 'max:100'],
            'signatory_1_title' => ['nullable', 'string', 'max:100'],
            'signatory_1_name' => ['nullable', 'string', 'max:100'],
            'signatory_2_title' => ['nullable', 'string', 'max:100'],
            'signatory_2_name' => ['nullable', 'string', 'max:100'],
        ]);

        Setting::current()->update(array_map(
            fn (?string $value): ?string => $value ?: null,
            $validated
        ));

        ActivityLogger::log(
            'settings.letter_updated',
            'Briefeinstellungen wurden geändert.'
        );

        session()->flash('success', 'Briefeinstellungen wurden gespeichert.');
    }

    /**
     * Uploads werden sofort nach der Auswahl gespeichert.
     */
    public function updated(string $property): void
    {
        if (! isset(self::UPLOAD_SLOTS[$property]) || $this->{$property} === null) {
            return;
        }

        $slot = self::UPLOAD_SLOTS[$property];

        if ($this->brandingLocked()) {
            $this->reset($property);

            return;
        }

        $this->validateOnly($property, [
            $property => $slot === 'letterhead'
                ? ['file', 'mimes:pdf', 'max:10240']
                : ['image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        app(ClubBranding::class)->store($slot, $this->{$property});

        $this->reset($property);

        ActivityLogger::log(
            'settings.branding_uploaded',
            'Datei „'.$this->slotLabel($slot).'“ wurde hochgeladen.'
        );

        session()->flash('success', $this->slotLabel($slot).' wurde gespeichert.');
    }

    public function removeFile(string $slot): void
    {
        if ($this->brandingLocked() || ! array_key_exists($slot, ClubBranding::SLOTS)) {
            return;
        }

        app(ClubBranding::class)->delete($slot);

        ActivityLogger::log(
            'settings.branding_removed',
            'Datei „'.$this->slotLabel($slot).'“ wurde entfernt.'
        );

        session()->flash('success', $this->slotLabel($slot).' wurde entfernt.');
    }

    public function saveMail(): void
    {
        $validated = $this->validate([
            'mail_from_address' => ['nullable', 'email', 'max:150'],
            'mail_from_name' => ['nullable', 'string', 'max:150'],
            'birthday_recipient_group' => ['nullable', 'string', Rule::exists(RecipientGroup::class, 'name')],
            'imap_host' => ['nullable', 'string', 'max:150'],
            'imap_port' => ['nullable', 'integer', 'between:1,65535'],
            'imap_encryption' => ['required', Rule::in(['ssl', 'tls', 'starttls', 'notls'])],
            'imap_validate_cert' => ['boolean'],
            'imap_username' => ['nullable', 'required_with:imap_host', 'string', 'max:150'],
            'imap_password' => ['nullable', 'string', 'max:255'],
            'imap_sent_folder' => ['nullable', 'string', 'max:150'],
        ]);

        $attributes = [
            'mail_from_address' => $validated['mail_from_address'] ?: null,
            'mail_from_name' => $validated['mail_from_name'] ?: null,
            'birthday_recipient_group' => $validated['birthday_recipient_group'] ?: null,
            'imap_host' => $validated['imap_host'] ?: null,
            'imap_port' => $validated['imap_port'] ?: null,
            'imap_encryption' => $validated['imap_encryption'],
            'imap_validate_cert' => $validated['imap_validate_cert'],
            'imap_username' => $validated['imap_username'] ?: null,
            'imap_sent_folder' => $validated['imap_sent_folder'] ?: null,
        ];

        if (filled($validated['imap_password'])) {
            $attributes['imap_password'] = $validated['imap_password'];
        }

        if ($attributes['imap_host'] === null) {
            $attributes['imap_password'] = null;
        }

        Setting::current()->update($attributes);

        $this->imap_password = '';

        ActivityLogger::log(
            'settings.mail_updated',
            'E-Mail-Einstellungen wurden geändert.'
        );

        session()->flash('success', 'E-Mail-Einstellungen wurden gespeichert.');
    }

    /**
     * Prüft das gespeicherte IMAP-Konto (eigenes oder aus .env).
     */
    public function testImap(ImapSentMailService $imapSentMailService): void
    {
        try {
            $client = $imapSentMailService->makeClient();
            $client->connect();

            $sentFolder = $imapSentMailService->accountConfig()['sent_folder'];
            $folderExists = $client->getFolderByPath($sentFolder) !== null;

            $client->disconnect();
        } catch (Throwable $exception) {
            session()->flash('error', 'IMAP-Verbindung fehlgeschlagen: '.$exception->getMessage());

            return;
        }

        if (! $folderExists) {
            session()->flash('error', 'IMAP-Verbindung erfolgreich, aber der Gesendet-Ordner „'.$sentFolder.'“ wurde nicht gefunden.');

            return;
        }

        session()->flash('success', 'IMAP-Verbindung erfolgreich, Gesendet-Ordner gefunden.');
    }

    /**
     * Dateien liegen außerhalb der Test-DB und würden im Testmodus die
     * Live-Dateien überschreiben.
     */
    private function brandingLocked(): bool
    {
        return app(DemoMode::class)->isActive();
    }

    private function slotLabel(string $slot): string
    {
        return match ($slot) {
            'logo' => 'Logo',
            'letterhead' => 'Briefpapier',
            'signature_1' => 'Unterschrift 1',
            'signature_2' => 'Unterschrift 2',
        };
    }

    public function render(ClubBranding $branding)
    {
        $setting = Setting::current();

        return view('livewire.admin.settings.documents', [
            'isDemoActive' => $this->brandingLocked(),
            'logoUrl' => $branding->logoUrl(),
            'hasLetterhead' => $branding->letterheadPath() !== null,
            'hasSignature' => [
                1 => $branding->path('signature_1') !== null,
                2 => $branding->path('signature_2') !== null,
            ],
            'hasStoredImapPassword' => filled($setting->imap_password),
            'defaultBirthdayGroup' => config('birthday.recipient_group'),
            'recipientGroups' => RecipientGroup::query()->orderBy('name')->pluck('name'),
        ])
            ->layout('layouts.app', [
                'title' => 'Briefpapier & E-Mail | VEMA',
                'heading' => 'Einstellungen',
            ]);
    }
}
