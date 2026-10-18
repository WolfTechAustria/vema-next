<?php

namespace App\Livewire\Homepage;

use App\Models\HomepageSetting;
use App\Models\Post;
use App\Services\ActivityLogger;
use App\Services\DemoMode;
use App\Services\MailPosts\MailPostImporter;
use App\Services\MailPosts\MailPostInbox;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Throwable;

/**
 * Beiträge per E-Mail: Anleitung und Abruf für Homepage-Verantwortliche,
 * Postfach-Zugangsdaten und Freigabeliste nur für Admins.
 */
class MailPosts extends Component
{
    /**
     * @var array<string, string>
     */
    private const ACTIVITY_LABELS = [
        'mail_posts_enabled' => 'Beiträge per E-Mail aktiv',
        'mail_posts_address' => 'Adresse',
        'mail_posts_host' => 'IMAP-Server',
        'mail_posts_port' => 'Port',
        'mail_posts_encryption' => 'Verschlüsselung',
        'mail_posts_validate_cert' => 'Zertifikat prüfen',
        'mail_posts_username' => 'Benutzername',
        'mail_posts_password' => 'Passwort',
        'mail_posts_folder' => 'Ordner',
        'mail_posts_processed_folder' => 'Verarbeitet-Ordner',
        'mail_posts_allowed_senders' => 'Weitere Absender',
    ];

    public bool $enabled = false;

    public string $address = '';

    public string $host = '';

    public ?int $port = 993;

    public string $encryption = 'ssl';

    public bool $validateCert = true;

    public string $username = '';

    public string $password = '';

    public string $folder = 'INBOX';

    public string $processedFolder = '';

    public string $allowedSenders = '';

    public function mount(): void
    {
        $settings = HomepageSetting::current();

        $this->enabled = $settings->mail_posts_enabled;
        $this->address = $settings->mail_posts_address ?? '';
        $this->host = $settings->mail_posts_host ?? '';
        $this->port = $settings->mail_posts_port ?: 993;
        $this->encryption = $settings->mail_posts_encryption ?: 'ssl';
        $this->validateCert = $settings->mail_posts_validate_cert;
        $this->username = $settings->mail_posts_username ?? '';
        $this->folder = $settings->mail_posts_folder ?: 'INBOX';
        $this->processedFolder = $settings->mail_posts_processed_folder ?? '';
        $this->allowedSenders = $settings->mail_posts_allowed_senders ?? '';
    }

    public function save(): void
    {
        $this->authorizeAdmin();

        $validated = $this->validate([
            'enabled' => ['boolean'],
            'address' => ['nullable', 'email', 'max:190'],
            'host' => ['nullable', 'required_if:enabled,true', 'string', 'max:190'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'encryption' => [Rule::in(['ssl', 'tls', 'starttls', 'notls'])],
            'validateCert' => ['boolean'],
            'username' => ['nullable', 'required_if:enabled,true', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:500'],
            'folder' => ['nullable', 'string', 'max:190'],
            'processedFolder' => ['nullable', 'string', 'max:190'],
            'allowedSenders' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'address' => 'Adresse',
            'host' => 'IMAP-Server',
            'username' => 'Benutzername',
        ]);

        $settings = HomepageSetting::current();

        if ($validated['enabled'] && blank($validated['password']) && blank($settings->mail_posts_password)) {
            $this->addError('password', 'Bitte das Passwort des Postfachs eingeben.');

            return;
        }

        $before = $this->snapshot($settings);

        $attributes = [
            'mail_posts_enabled' => $validated['enabled'],
            'mail_posts_address' => $validated['address'] ?: null,
            'mail_posts_host' => $validated['host'] ?: null,
            'mail_posts_port' => $validated['port'] ?: null,
            'mail_posts_encryption' => $validated['encryption'],
            'mail_posts_validate_cert' => $validated['validateCert'],
            'mail_posts_username' => $validated['username'] ?: null,
            'mail_posts_folder' => $validated['folder'] ?: null,
            'mail_posts_processed_folder' => $validated['processedFolder'] ?: null,
            'mail_posts_allowed_senders' => $validated['allowedSenders'] ?: null,
        ];

        // Passwort nur bei Neueingabe überschreiben; ohne Server entfällt es.
        if (filled($validated['password'])) {
            $attributes['mail_posts_password'] = $validated['password'];
        }

        if (blank($validated['host'])) {
            $attributes['mail_posts_password'] = null;
        }

        $settings->update($attributes);
        $this->password = '';

        $changes = ActivityLogger::changes($before, $this->snapshot($settings->fresh()), self::ACTIVITY_LABELS, ['mail_posts_password']);

        if ($changes !== []) {
            ActivityLogger::log(
                'homepage.mail_posts_updated',
                'Einstellungen für Beiträge per E-Mail wurden geändert.',
                'HomepageSetting',
                $settings->homepageSettingID,
                $changes
            );
        }

        session()->flash('success', 'Einstellungen wurden gespeichert.');
    }

    public function testConnection(MailPostInbox $inbox): void
    {
        $this->authorizeAdmin();

        try {
            $unread = $inbox->test(HomepageSetting::current());
            session()->flash('success', 'Verbindung erfolgreich – '.$unread.' ungelesene Nachricht(en) im Ordner.');
        } catch (Throwable $exception) {
            session()->flash('error', 'Verbindung fehlgeschlagen: '.$exception->getMessage());
        } finally {
            $inbox->disconnect();
        }
    }

    public function fetchNow(MailPostImporter $importer, DemoMode $demoMode): void
    {
        $settings = HomepageSetting::current();

        if (! $settings->mail_posts_enabled || ! $settings->hasMailPostAccount()) {
            session()->flash('error', 'Beiträge per E-Mail sind noch nicht eingerichtet.');

            return;
        }

        if ($demoMode->isActive()) {
            session()->flash('warning', 'Im Testmodus wird das Postfach nicht abgerufen.');

            return;
        }

        try {
            $result = $importer->fetch($settings);
        } catch (Throwable $exception) {
            session()->flash('error', 'Postfach konnte nicht abgerufen werden: '.$exception->getMessage());

            return;
        }

        session()->flash('success', $result['created'] === 0
            ? 'Keine neuen Beiträge im Postfach.'
            : $result['created'].' neue(r) Beitrag/Beiträge als Entwurf angelegt.');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(HomepageSetting $settings): array
    {
        return $settings->only(array_keys(self::ACTIVITY_LABELS));
    }

    public function render()
    {
        $settings = HomepageSetting::current();

        return view('livewire.homepage.mail-posts', [
            'settings' => $settings,
            'isAdmin' => auth()->user()?->hasRole('admin') ?? false,
            'hasPassword' => filled($settings->mail_posts_password),
            'recentPosts' => Post::query()->whereNotNull('submitted_by_email')->latest('postID')->limit(5)->get(),
        ])->layout('layouts.app', [
            'title' => 'Beiträge per E-Mail | VEMA',
            'heading' => 'Homepage',
        ]);
    }
}
