<?php

namespace App\Console\Commands;

use App\Enums\Feature;
use App\Models\HomepageSetting;
use App\Services\DemoMode;
use App\Services\MailPosts\MailPostImporter;
use App\Services\PlanEntitlements;
use Illuminate\Console\Command;
use Throwable;

class HomepageFetchMailPosts extends Command
{
    protected $signature = 'homepage:fetch-mail-posts';

    protected $description = 'Ruft das Beitrags-Postfach der Homepage ab und legt Beiträge als Entwurf an';

    public function handle(PlanEntitlements $entitlements, DemoMode $demoMode, MailPostImporter $importer): int
    {
        $settings = HomepageSetting::current();

        if (! $entitlements->allows(Feature::Homepage) || ! $settings->mail_posts_enabled || ! $settings->hasMailPostAccount() || $demoMode->isActive()) {
            return self::SUCCESS;
        }

        try {
            $result = $importer->fetch($settings);
        } catch (Throwable $exception) {
            $this->error('Postfach konnte nicht abgerufen werden: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%d neue Beiträge, %d abgelehnt, %d doppelt, %d fehlerhaft.',
            $result['created'],
            $result['rejected'],
            $result['duplicates'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
