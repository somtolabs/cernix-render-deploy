<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CernixReset extends Command
{
    protected $signature   = 'cernix:reset {--force : Skip confirmation prompt}';
    protected $description = 'Dev-only: clear student registrations and reset mock photo paths for clean testing';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('This command cannot run in production.');
            return 1;
        }

        if (! $this->option('force') && ! $this->confirm('This will delete all students, tokens, and logs. Continue?')) {
            $this->line('Aborted.');
            return 0;
        }

        DB::table('verification_logs')->delete();
        DB::table('audit_log')->delete();
        DB::table('payment_records')->delete();
        DB::table('qr_tokens')->delete();
        DB::table('students')->delete();

        // Restore mock SIS photo paths to seeder defaults
        $defaults = [
            'CSC/2021/001' => 'photos/student1.jpg',
            'SEN/2021/002' => 'photos/student2.jpg',
            'IFT/2021/003' => 'photos/student3.jpg',
            'CYS/2021/004' => 'photos/student4.jpg',
            'DTS/2021/005' => 'photos/student5.jpg',
        ];

        foreach ($defaults as $matric => $path) {
            DB::table('mock_sis')
                ->where('matric_no', $matric)
                ->update(['photo_path' => $path]);
        }

        // Remove any uploaded photos (keep seeded photos)
        $uploadPattern = public_path('photos') . '/upload_*.jpg';
        foreach (glob($uploadPattern) ?: [] as $file) {
            @unlink($file);
        }

        $this->info('Reset complete. Students, tokens, and upload photos cleared.');
        $this->line('Mock SIS photo paths restored to defaults.');

        return 0;
    }
}
