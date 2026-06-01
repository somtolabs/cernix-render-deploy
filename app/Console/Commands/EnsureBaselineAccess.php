<?php

namespace App\Console\Commands;

use App\Services\BaselineAccessService;
use Illuminate\Console\Command;

class EnsureBaselineAccess extends Command
{
    protected $signature = 'cernix:ensure-baseline-access';

    protected $description = 'Safely repair the baseline staff accounts without changing runtime activity';

    public function handle(BaselineAccessService $baselineAccess): int
    {
        foreach ($baselineAccess->ensure() as $username) {
            $this->line("Ensured baseline staff account: {$username}");
        }

        $this->info('Baseline staff access ensured without modifying runtime activity.');

        return self::SUCCESS;
    }
}