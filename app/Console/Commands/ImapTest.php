<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\PHPIMAP\ClientManager;

class ImapTest extends Command
{
    protected $signature = 'imap:test';

    protected $description = 'Testet die IMAP-Verbindung und listet alle Ordner auf';

    public function handle(): int
    {
        $cm = new ClientManager();

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

        try {
            $client->connect();

            $this->info('IMAP-Verbindung erfolgreich.');

            $folders = $client->getFolders(false);

            $this->newLine();
            $this->info('Gefundene Ordner:');

            foreach ($folders as $folder) {
                $this->line(
                    '- Name: ' . $folder->name
                    . ' | Pfad: ' . $folder->path
                    . ' | Full: ' . $folder->full_name
                );
            }

            $client->disconnect();

            return self::SUCCESS;

        } catch (\Throwable $e) {

            $this->error(
                'IMAP-Fehler: ' . $e->getMessage()
            );

            return self::FAILURE;
        }
    }
}
