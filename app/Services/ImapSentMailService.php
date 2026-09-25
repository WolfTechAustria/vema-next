<?php

namespace App\Services;

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

        $cm = new ClientManager;

        $client = $cm->make([
            'host' => env('IMAP_HOST'),
            'port' => (int) env('IMAP_PORT', 993),
            'encryption' => env('IMAP_ENCRYPTION', 'ssl'),
            'validate_cert' => filter_var(
                env('IMAP_VALIDATE_CERT', true),
                FILTER_VALIDATE_BOOLEAN
            ),
            'username' => env('IMAP_USERNAME'),
            'password' => env('IMAP_PASSWORD'),
            'protocol' => 'imap',
        ]);

        $client->connect();

        try {

            $sentFolderPath = env('IMAP_SENT_FOLDER', 'INBOX.Sent');

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
}
