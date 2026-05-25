<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TimetableSeeder extends Seeder
{
    public function run(): void
    {
        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        if (! $session || ! DB::getSchemaBuilder()->hasTable('timetables')) {
            return;
        }

        $courses = [
            'Computer Science' => [
                '300' => [['CSC301', 'Operating Systems', 1, '09:00', '12:00', 'CBT Hall A']],
                '400' => [['CSC401', 'Artificial Intelligence', 0, '10:00', '13:00', 'Faculty Lab 1'], ['CSC405', 'Distributed Systems', 4, '09:00', '12:00', 'CBT Hall B']],
            ],
            'Software Engineering' => [
                '300' => [['SEN301', 'Software Architecture', 1, '09:00', '12:00', 'CBT Hall C']],
                '400' => [['SEN401', 'Secure Software Engineering', 3, '10:00', '13:00', 'Engineering Lab']],
            ],
            'Information Technology' => [
                '300' => [['IFT301', 'Network Administration', 2, '09:00', '12:00', 'ICT Hall']],
                '400' => [['IFT401', 'Cloud Infrastructure', 4, '10:00', '13:00', 'CBT Hall A']],
            ],
            'Cyber Security' => [
                '300' => [['CYS301', 'Cryptography', 0, '09:00', '12:00', 'Security Lab']],
                '400' => [['CYS401', 'Digital Forensics', 5, '10:00', '13:00', 'Forensics Lab']],
            ],
            'Data Science' => [
                '300' => [['DTS301', 'Statistical Computing', 2, '09:00', '12:00', 'CBT Hall D']],
                '400' => [['DTS401', 'Machine Learning', 6, '10:00', '13:00', 'Data Lab']],
            ],
        ];

        foreach ($courses as $departmentName => $levels) {
            $department = DB::table('departments')->where('dept_name', $departmentName)->first();
            if (! $department) {
                continue;
            }

            foreach ($levels as $level => $entries) {
                foreach ($entries as [$code, $title, $dayOffset, $start, $end, $venue]) {
                    DB::table('timetables')->updateOrInsert(
                        [
                            'exam_session_id' => $session->session_id,
                            'department_id' => $department->dept_id,
                            'level' => $level,
                            'course_code' => $code,
                            'exam_date' => Carbon::today()->addDays($dayOffset)->toDateString(),
                            'start_time' => $start,
                        ],
                        [
                            'course_title' => $title,
                            'end_time' => $end,
                            'venue' => $venue,
                            'status' => 'scheduled',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }
    }
}
