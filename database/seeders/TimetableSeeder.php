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
                '100' => ['CSC101', 'Introduction to Computer Science', 'CBT Hall A'],
                '200' => ['CSC201', 'Computer Programming II', 'CBT Hall B'],
                '300' => ['CSC301', 'Operating Systems', 'CBT Hall A'],
                '400' => ['CSC401', 'Artificial Intelligence', 'Faculty Lab 1'],
            ],
            'Software Engineering' => [
                '100' => ['SEN101', 'Foundations of Software Engineering', 'CBT Hall C'],
                '200' => ['SEN201', 'Object-Oriented Programming', 'Engineering Hall'],
                '300' => ['SEN301', 'Software Architecture', 'CBT Hall C'],
                '400' => ['SEN401', 'Secure Software Engineering', 'Engineering Lab'],
            ],
            'Information Technology' => [
                '100' => ['IFT101', 'Introduction to Information Technology', 'ICT Hall'],
                '200' => ['IFT201', 'Database Systems', 'CBT Hall A'],
                '300' => ['IFT301', 'Network Administration', 'ICT Hall'],
                '400' => ['IFT401', 'Cloud Infrastructure', 'CBT Hall A'],
            ],
            'Cyber Security' => [
                '100' => ['CYS101', 'Cyber Security Fundamentals', 'CBT Hall B'],
                '200' => ['CYS201', 'Network Security', 'Security Lab'],
                '300' => ['CYS301', 'Cryptography', 'Security Lab'],
                '400' => ['CYS401', 'Digital Forensics', 'Forensics Lab'],
            ],
            'Data Science' => [
                '100' => ['DTS101', 'Introduction to Data Science', 'CBT Hall D'],
                '200' => ['DTS201', 'Probability and Statistics', 'Science Hall'],
                '300' => ['DTS301', 'Statistical Computing', 'CBT Hall D'],
                '400' => ['DTS401', 'Machine Learning', 'Data Lab'],
            ],
        ];

        $dayOffset = 1;
        foreach ($courses as $departmentName => $levels) {
            $department = DB::table('departments')->where('dept_name', $departmentName)->first();
            if (! $department) {
                continue;
            }

            foreach ($levels as $level => [$code, $title, $venue]) {
                $exists = DB::table('timetables')
                    ->where('exam_session_id', $session->session_id)
                    ->where('department_id', $department->dept_id)
                    ->where('level', $level)
                    ->where('course_code', $code)
                    ->exists();

                if (! $exists) {
                    DB::table('timetables')->insert([
                        'exam_session_id' => $session->session_id,
                        'department_id' => $department->dept_id,
                        'level' => $level,
                        'course_code' => $code,
                        'course_title' => $title,
                        'exam_date' => Carbon::today()->addDays($dayOffset)->toDateString(),
                        'start_time' => $level === '400' ? '13:00' : '09:00',
                        'end_time' => $level === '400' ? '16:00' : '12:00',
                        'venue' => $venue,
                        'status' => 'scheduled',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $dayOffset++;
            }
        }
    }
}
