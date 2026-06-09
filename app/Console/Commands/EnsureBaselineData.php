<?php

namespace App\Console\Commands;

use App\Services\BaselineAccessService;
use App\Services\BaselineSessionService;
use Database\Seeders\DepartmentsSeeder;
use Database\Seeders\TimetableSeeder;
use Illuminate\Console\Command;

class EnsureBaselineData extends Command
{
    protected $signature = 'cernix:repair-baseline {--force : Allow non-interactive production deployment repair}';

    protected $description = 'Safely repair required departments, session, timetable, and baseline staff access';

    public function handle(BaselineSessionService $baselineSession, BaselineAccessService $baselineAccess): int
    {
        app(DepartmentsSeeder::class)->run();
        $sessionId = $baselineSession->ensure();
        $usernames = $baselineAccess->ensure();
        app(TimetableSeeder::class)->run();

        $this->line("Ensured active registration session: {$sessionId}");
        $this->line('Ensured registration departments.');
        $this->line('Ensured missing baseline timetable rows.');
        $this->line('Ensured baseline staff accounts: '.implode(', ', $usernames));
        $this->info('Baseline data ensured without modifying runtime activity.');

        return self::SUCCESS;
    }
}
