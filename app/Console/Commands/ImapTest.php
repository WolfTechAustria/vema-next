<?php

namespace App\Console\Commands;

use App\Services\ImapSentMailService;
use Illuminate\Console\Command;

class ImapTest extends Command
{
    protected $signature = 'imap:test';

    protected $description = 'Testet die IMAP-Verbindung und listet alle Ordner auf';

    public function handle(ImapSentMailService $imapSentMailService): int
    {
        $client = $imapSentMailService->makeClient();

        try {
            $client->connect();

            $this->info('IMAP-Verbindung erfolgreich.');

            $folders = $client->getFolders(false);

            $this->newLine();
            $this->info('Gefundene Ordner:');

            foreach ($folders as $folder) {
                $this->line(
                    '- Name: '.$folder->name
                    .' | Pfad: '.$folder->path
                    .' | Full: '.$folder->full_name
                );
            }

            $client->disconnect();

            return self::SUCCESS;

        } catch (\Throwable $e) {

            $this->error(
                'IMAP-Fehler: '.$e->getMessage()
            );

            return self::FAILURE;
        }
    }
}
