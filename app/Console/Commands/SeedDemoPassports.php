<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SeedDemoPassports extends Command
{
    protected $signature = 'cernix:seed-demo-passports';

    protected $description = 'Verify supplied local demo passport portraits and map them to demo mock SIS students.';

    private array $demoStudents = [
        '230405001' => ['name' => 'Chidera Favour Nnamdi', 'file' => 'student-001.jpg', 'department' => 'Software Engineering', 'department_code' => '05', 'level' => '300'],
        '250404002' => ['name' => 'Ifeoma Grace Okafor', 'file' => 'student-002.jpg', 'department' => 'Computer Science', 'department_code' => '04', 'level' => '100'],
        '240406003' => ['name' => 'Chiamaka Ruth Eze', 'file' => 'student-003.jpg', 'department' => 'Information Technology', 'department_code' => '06', 'level' => '200'],
        '220405004' => ['name' => 'Adaeze Jennifer Obi', 'file' => 'student-004.jpg', 'department' => 'Software Engineering', 'department_code' => '05', 'level' => '400'],
        '230404005' => ['name' => 'Tunde Michael Bello', 'file' => 'student-005.jpg', 'department' => 'Computer Science', 'department_code' => '04', 'level' => '300'],
        '220406006' => ['name' => 'Ayomide Samuel Adeyemi', 'file' => 'student-006.jpg', 'department' => 'Information Technology', 'department_code' => '06', 'level' => '400'],
        '240407007' => ['name' => 'Somtochukwu David Okafor', 'file' => 'student-007.jpg', 'department' => 'Cyber Security', 'department_code' => '07', 'level' => '200'],
        '220404008' => ['name' => 'Chukwuemeka Daniel Nwosu', 'file' => 'student-008.jpg', 'department' => 'Computer Science', 'department_code' => '04', 'level' => '400'],
        '250408009' => ['name' => 'Toluwani Deborah Akinola', 'file' => 'student-009.jpg', 'department' => 'Data Science', 'department_code' => '08', 'level' => '100'],
        '230408010' => ['name' => 'Amara Blessing Nwankwo', 'file' => 'student-010.jpg', 'department' => 'Data Science', 'department_code' => '08', 'level' => '300'],
        '250405011' => ['name' => 'Femi Joshua Akinola', 'file' => 'student-011.jpg', 'department' => 'Software Engineering', 'department_code' => '05', 'level' => '100'],
        '240404012' => ['name' => 'Ibrahim Musa Adamu', 'file' => 'student-012.jpg', 'department' => 'Computer Science', 'department_code' => '04', 'level' => '200'],
        '230407013' => ['name' => 'Emeka Kingsley Obi', 'file' => 'student-013.jpg', 'department' => 'Cyber Security', 'department_code' => '07', 'level' => '300'],
        '220408014' => ['name' => 'Uche David Nnamdi', 'file' => 'student-014.jpg', 'department' => 'Data Science', 'department_code' => '08', 'level' => '400'],
    ];

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->warn('Skipped: demo passport mapping is disabled in production.');
            return self::SUCCESS;
        }

        $directory = public_path('demo-passports');
        File::ensureDirectoryExists($directory);

        $missing = collect($this->demoStudents)
            ->pluck('file')
            ->unique()
            ->reject(fn (string $file) => File::exists($directory . DIRECTORY_SEPARATOR . $file))
            ->values();

        if ($missing->isNotEmpty()) {
            $this->error('Missing supplied demo passport image(s): ' . $missing->implode(', '));
            $this->line('Add the provided images to public/demo-passports/ using student-001.jpg through student-014.jpg.');
            return self::FAILURE;
        }

        foreach ($this->demoStudents as $matricNo => $meta) {
            $path = 'demo-passports/' . $meta['file'];

            DB::table('mock_sis')
                ->updateOrInsert(['matric_no' => $matricNo], [
                    'full_name' => $meta['name'],
                    'department' => $meta['department'],
                    'department_code' => $meta['department_code'],
                    'faculty_code' => '04',
                    'level' => $meta['level'],
                    'photo_path' => $path,
                ]);

            DB::table('students')
                ->where('matric_no', $matricNo)
                ->update([
                    'full_name' => $meta['name'],
                    'photo_path' => $path,
                ]);
        }

        $this->info('Supplied local demo passport portraits are mapped in mock SIS and existing demo student records.');
        $this->line('No external portrait URLs, generated placeholders, or project documentation photos are used for student identity.');

        return self::SUCCESS;
    }
}
