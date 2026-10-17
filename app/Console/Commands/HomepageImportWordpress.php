<?php

namespace App\Console\Commands;

use App\Services\WordPressPostImporter;
use Illuminate\Console\Command;
use Throwable;

class HomepageImportWordpress extends Command
{
    protected $signature = 'homepage:import-wordpress
        {url : Adresse der bisherigen WordPress-Seite, z. B. https://sg-angerberg.at}
        {--dry-run : Nur anzeigen, was übernommen würde}';

    protected $description = 'Übernimmt die Beiträge einer WordPress-Seite (Text, Bilder, PDFs) als News der Vereinshomepage';

    public function handle(WordPressPostImporter $importer): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $importer->import(
                (string) $this->argument('url'),
                $dryRun,
                fn (string $message) => $this->line('  '.$message)
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d Beiträge, %d Bilder, %d PDFs – %d bereits vorhanden.',
            $dryRun ? 'Testlauf' : 'Fertig',
            $result['imported'],
            $result['images'],
            $result['attachments'],
            $result['skipped'],
        ));

        if ($result['failed'] !== []) {
            $this->warn(count($result['failed']).' Dateien konnten nicht übernommen werden:');

            foreach ($result['failed'] as $failure) {
                $this->line('  - '.$failure);
            }
        }

        return self::SUCCESS;
    }
}
