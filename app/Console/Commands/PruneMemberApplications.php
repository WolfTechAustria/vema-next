<?php

namespace App\Console\Commands;

use App\Services\MemberApplications;
use Illuminate\Console\Command;

class PruneMemberApplications extends Command
{
    protected $signature = 'applications:prune';

    protected $description = 'Löscht Online-Anmeldungen, deren E-Mail-Adresse nicht bestätigt wurde';

    public function handle(MemberApplications $applications): int
    {
        $count = $applications->pruneUnverified();

        $this->info($count.' unbestätigte Online-Anmeldung(en) gelöscht.');

        return self::SUCCESS;
    }
}
