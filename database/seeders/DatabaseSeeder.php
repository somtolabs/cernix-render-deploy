<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DepartmentsSeeder::class,
            ExamSessionsSeeder::class,
            CernixSettingsSeeder::class,
            MockSISSeeder::class,
            ExaminersSeeder::class,
            TimetableSeeder::class,
        ]);
    }
}
