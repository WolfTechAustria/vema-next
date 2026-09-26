<?php

namespace App\Services;

use App\Models\Setting;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

class ImapSentMailService
{
    public function append(
        string $rawMessage
    ): void {
        // Testmodus: nichts in den echten Gesendet-Ordner legen.
        if (app(DemoMode::class)->isActive()) {
            return;
        }

        $client = $this->makeClient();

        $client->connect();

        try {

            $sentFolderPath = $this->accountConfig()['sent_folder'];

            $folder = $client->getFolderByPath(
                $sentFolderPath
            );

            if (! $folder) {
                throw new \RuntimeException(
                    'IMAP Sent-Ordner wurde nicht gefunden: '.$sentFolderPath
                );
            }

            $folder->appendMessage(
                $rawMessage,
                ['\\Seen']
            );

        } finally {
            $client->disconnect();
        }
    }

    public function makeClient(): Client
    {
        $account = $this->accountConfig();

        return (new ClientManager)->make([
            'host' => $account['host'],
            'port' => $account['port'],
            'encryption' => $account['encryption'],
            'validate_cert' => $account['validate_cert'],
            'username' => $account['username'],
            'password' => $account['password'],
            'protocol' => 'imap',
        ]);
    }

    /**
     * IMAP-Konto aus den Vereinseinstellungen; ohne eigenen Host gilt das
     * Konto aus .env (config/imap.php).
     *
     * @return array{host: ?string, port: int, encryption: string, validate_cert: bool, username: ?string, password: ?string, sent_folder: string}
     */
    public function accountConfig(): array
    {
        $setting = Setting::current();

        if ($setting->hasOwnImapAccount()) {
            return [
                'host' => $setting->imap_host,
                'port' => $setting->imap_port ?: 993,
                'encryption' => $setting->imap_encryption ?: 'ssl',
                'validate_cert' => (bool) $setting->imap_validate_cert,
                'username' => $setting->imap_username,
                'password' => $setting->imap_password,
                'sent_folder' => $setting->imap_sent_folder ?: 'INBOX.Sent',
            ];
        }

        $default = config('imap.accounts.default');

        return [
            'host' => $default['host'],
            'port' => (int) $default['port'],
            'encryption' => (string) $default['encryption'],
            'validate_cert' => filter_var($default['validate_cert'], FILTER_VALIDATE_BOOLEAN),
            'username' => $default['username'],
            'password' => $default['password'],
            'sent_folder' => (string) ($default['sent_folder'] ?? 'INBOX.Sent'),
        ];
    }
}
