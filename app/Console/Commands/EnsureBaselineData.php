<?php

namespace App\Console\Commands;

use App\Services\BaselineAccessService;
use App\Services\BaselineSessionService;
use Database\Seeders\DepartmentsSeeder;
use Illuminate\Console\Command;

class EnsureBaselineData extends Command
{
    protected $signature = 'cernix:ensure-baseline-data';

    protected $description = 'Safely ensure registration reference data, one active session, and baseline staff access';

    public function handle(BaselineSessionService $baselineSession, BaselineAccessService $baselineAccess): int
    {
        app(DepartmentsSeeder::class)->run();
        $sessionId = $baselineSession->ensure();
        $usernames = $baselineAccess->ensure();

        $this->line("Ensured active registration session: {$sessionId}");
        $this->line('Ensured registration departments.');
        $this->line('Ensured baseline staff accounts: '.implode(', ', $usernames));
        $this->info('Baseline data ensured without modifying runtime activity.');

        return self::SUCCESS;
    }
}
