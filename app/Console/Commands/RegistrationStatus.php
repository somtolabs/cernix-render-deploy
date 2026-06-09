<?php

namespace App\Console\Commands;

use App\Support\DepartmentFees;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RegistrationStatus extends Command
{
    protected $signature = 'cernix:registration-status';

    protected $description = 'Report non-secret registration runtime readiness';

    public function handle(): int
    {
        $this->line('Registration runtime status:');
        $this->line('  database_driver=' . DB::getDriverName());
        $this->line('  demo_mode=' . (DepartmentFees::isDemoMode() ? 'enabled' : 'disabled'));

        foreach (['departments', 'exam_sessions', 'mock_sis', 'students', 'sessions'] as $table) {
            $this->line("  table_{$table}=" . (Schema::hasTable($table) ? 'ready' : 'missing'));
        }

        if (Schema::hasTable('departments')) {
            $this->line('  department_count=' . DB::table('departments')->count());
        }

        if (Schema::hasTable('exam_sessions')) {
            $this->line('  active_session_count=' . DB::table('exam_sessions')->where('is_active', true)->count());
        }

        if (Schema::hasTable('mock_sis')) {
            $this->line('  mock_sis_count=' . DB::table('mock_sis')->count());
        }

        $requiredColumns = [
            'departments' => ['department_code', 'faculty_code'],
            'mock_sis' => ['department_code', 'faculty_code', 'level'],
            'students' => ['session_id', 'level', 'department_code', 'faculty_code'],
        ];

        foreach ($requiredColumns as $table => $columns) {
            foreach ($columns as $column) {
                $ready = Schema::hasTable($table) && Schema::hasColumn($table, $column);
                $this->line("  column_{$table}_{$column}=" . ($ready ? 'ready' : 'missing'));
            }
        }

        return self::SUCCESS;
    }
}
