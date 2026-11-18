<?php

namespace Database\Seeders;

use App\Models\OfficialStudent;
use App\Services\MatriculationNumberService;
use App\Support\Roles;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class VisualAuditSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure Exam Session
        $session = DB::table('exam_sessions')->where('session_id', 1)->first();
        if (!$session) {
            DB::table('exam_sessions')->insert([
                'session_id' => 1,
                'semester' => 'First Semester',
                'academic_year' => '2025/2026',
                'fee_amount' => 100000,
                'aes_key' => base64_encode(random_bytes(32)),
                'hmac_secret' => base64_encode(random_bytes(32)),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Ensure Departments
        $depts = [
            1 => ['Computer Science', '04', '04', 'Faculty of Computing'],
            2 => ['Software Engineering', '05', '04', 'Faculty of Computing'],
            3 => ['Information Technology', '06', '04', 'Faculty of Computing'],
            4 => ['Cyber Security', '07', '04', 'Faculty of Computing'],
            5 => ['Data Science', '08', '04', 'Faculty of Computing'],
        ];

        foreach ($depts as $id => [$name, $deptCode, $facCode, $fac]) {
            DB::table('departments')->updateOrInsert(
                ['dept_id' => $id],
                [
                    'dept_name' => $name,
                    'department_code' => $deptCode,
                    'faculty_code' => $facCode,
                    'faculty' => $fac,
                ]
            );
        }

        // 3. Ensure Staff Accounts
        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();
        if (!$examiner) {
            $examinerId = DB::table('examiners')->insertGetId([
                'username' => 'examiner1',
                'full_name' => 'Prof. Olumide Johnson-Alabi',
                'password_hash' => Hash::make('examiner123'),
                'role' => 'examiner',
                'is_active' => true,
                'created_at' => now(),
            ], 'examiner_id');
        } else {
            $examinerId = $examiner->examiner_id;
        }

        $superadmin = DB::table('examiners')->where('username', 'superadmin')->first();
        if (!$superadmin) {
            DB::table('examiners')->insert([
                'username' => 'superadmin',
                'full_name' => 'Super Administrator (Dr. K. A. Adebayo)',
                'password_hash' => Hash::make('superadmin123'),
                'role' => Roles::SUPER_ADMIN,
                'is_active' => true,
                'created_at' => now(),
            ]);
        }

        // 4. Realistic Student Registry Candidates (Long names, various departments, levels, years)
        $matricService = app(MatriculationNumberService::class);
        $candidates = [
            [
                'matric' => '220404001',
                'name' => 'OGUNDIPE BABATUNDE OLUWASEGUN EMMANUEL',
                'dept' => 'Computer Science',
                'fac' => 'Faculty of Computing',
                'level' => '400',
                'status' => 'active',
                'admission_year' => 2022,
                'has_portal' => true,
                'acc_status' => 'active',
                'photo_status' => 'approved',
                'paid' => true,
            ],
            [
                'matric' => '220404002',
                'name' => 'ABDULRAHMAN AISHA MOHAMMED-BELLO',
                'dept' => 'Computer Science',
                'fac' => 'Faculty of Computing',
                'level' => '400',
                'status' => 'active',
                'admission_year' => 2022,
                'has_portal' => true,
                'acc_status' => 'active',
                'photo_status' => 'approved',
                'paid' => true,
            ],
            [
                'matric' => '230405003',
                'name' => 'CHUKWUEMEKA-NWOSU CHINELO CHIDINMA',
                'dept' => 'Software Engineering',
                'fac' => 'Faculty of Computing',
                'level' => '300',
                'status' => 'active',
                'admission_year' => 2023,
                'has_portal' => true,
                'acc_status' => 'created',
                'photo_status' => 'pending_admin_approval',
                'paid' => true,
            ],
            [
                'matric' => '230406004',
                'name' => 'OLUWAFEMI ADEBAYO DANIEL-PETERS',
                'dept' => 'Information Technology',
                'fac' => 'Faculty of Computing',
                'level' => '300',
                'status' => 'active',
                'admission_year' => 2023,
                'has_portal' => false,
            ],
            [
                'matric' => '240407005',
                'name' => 'IBRAHIM-FAGBEMI FATIMA OMOLABAKE',
                'dept' => 'Cyber Security',
                'fac' => 'Faculty of Computing',
                'level' => '200',
                'status' => 'active',
                'admission_year' => 2024,
                'has_portal' => true,
                'acc_status' => 'inactive',
                'photo_status' => 'rejected',
                'paid' => false,
            ],
            [
                'matric' => '240408006',
                'name' => 'ADEWALE-KALEJAIYE SAMUEL TOBILOBISHO',
                'dept' => 'Data Science',
                'fac' => 'Faculty of Computing',
                'level' => '200',
                'status' => 'inactive',
                'admission_year' => 2024,
                'has_portal' => false,
            ],
            [
                'matric' => '250404007',
                'name' => 'OKAFOR-ANYANWU VALENTINE CHUKWUDI',
                'dept' => 'Computer Science',
                'fac' => 'Faculty of Computing',
                'level' => '100',
                'status' => 'active',
                'admission_year' => 2025,
                'has_portal' => false,
            ],
            [
                'matric' => '250405008',
                'name' => 'BELLO-OSAGIE MARGARET ESEOHE',
                'dept' => 'Software Engineering',
                'fac' => 'Faculty of Computing',
                'level' => '100',
                'status' => 'active',
                'admission_year' => 2025,
                'has_portal' => false,
            ],
            [
                'matric' => 'CSC/2021/009',
                'name' => 'OLATUNBOSUN VICTORIA MODUPEOLA-ADETOKUNBO',
                'dept' => 'Computer Science',
                'fac' => 'Faculty of Computing',
                'level' => '400',
                'status' => 'active',
                'admission_year' => 2021,
                'has_portal' => false,
            ],
            [
                'matric' => '220404010',
                'name' => 'NWANERI BLESSING NGOZIKA-PRISCILLIA',
                'dept' => 'Computer Science',
                'fac' => 'Faculty of Computing',
                'level' => '400',
                'status' => 'active',
                'admission_year' => 2022,
                'has_portal' => false,
            ],
            [
                'matric' => '220404007',
                'name' => 'Adeyemi Oluwaseun Miracle',
                'dept' => 'Computer Science',
                'fac' => 'Faculty of Computing',
                'level' => '400',
                'status' => 'active',
                'admission_year' => 2022,
                'has_portal' => true,
                'acc_status' => 'active',
                'photo_status' => 'approved',
                'paid' => true,
            ],
        ];

        foreach ($candidates as $c) {
            $parsed = $matricService->parse($c['matric'], verifyRegistry: false);
            $norm = $parsed->normalized_matric ?? trim($c['matric']);

            DB::table('official_students')->updateOrInsert(
                ['normalized_matric_number' => $norm],
                [
                    'matric_number' => $c['matric'],
                    'full_name' => $c['name'],
                    'department' => $c['dept'],
                    'faculty' => $c['fac'],
                    'level' => $c['level'],
                    'status' => $c['status'],
                    'admission_year' => $c['admission_year'],
                    'faculty_code' => '04',
                    'department_code' => '04',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            if (!empty($c['has_portal'])) {
                DB::table('students')->updateOrInsert(
                    ['matric_no' => $c['matric']],
                    [
                        'full_name' => $c['name'],
                        'department_id' => 1,
                        'session_id' => 1,
                        'account_status' => $c['acc_status'] ?? 'active',
                        'photo_status' => $c['photo_status'] ?? 'approved',
                        'photo_path' => 'photos/test.jpg',
                        'profile_photo_path' => 'photos/test.jpg',
                        'password' => Hash::make('student123'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                if (!empty($c['paid'])) {
                    DB::table('payment_records')->updateOrInsert(
                        ['student_id' => $c['matric'], 'session_id' => 1],
                        [
                            'rrr_number' => 'RRR-' . substr(md5($c['matric']), 0, 12),
                            'amount_declared' => 100000,
                            'amount_confirmed' => 100000,
                            'remita_response' => json_encode(['status' => '00', 'message' => 'Approved']),
                            'verified_at' => now(),
                        ]
                    );
                }
            }
        }

        // 5. Timetable Scheduled Assessment
        $timetableId = DB::table('timetables')->updateOrInsert(
            [
                'exam_session_id' => 1,
                'course_code' => 'CSC401',
            ],
            [
                'department_id' => 1,
                'level' => '400',
                'course_title' => 'Advanced Artificial Intelligence & Neural Networks',
                'exam_date' => Carbon::today()->toDateString(),
                'start_time' => '13:00',
                'end_time' => '16:00',
                'venue' => 'CBT Examination Hall A (Main Campus)',
                'status' => 'active',
                'assessment_type' => 'exam',
                'register_status' => 'confirmed',
                'roster_confirmed_at' => now(),
                'examiner_id' => $examinerId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $tRow = DB::table('timetables')->where('course_code', 'CSC401')->first();
        $tId = $tRow->id;

        // Second Timetable (Incomplete register)
        DB::table('timetables')->updateOrInsert(
            [
                'exam_session_id' => 1,
                'course_code' => 'CSC403',
            ],
            [
                'department_id' => 1,
                'level' => '400',
                'course_title' => 'Distributed Computing Systems & Cloud Infrastructure',
                'exam_date' => Carbon::today()->addDay()->toDateString(),
                'start_time' => '09:00',
                'end_time' => '12:00',
                'venue' => 'Science Lecture Theatre II (SLT-2)',
                'status' => 'scheduled',
                'assessment_type' => 'exam',
                'register_status' => 'incomplete',
                'examiner_id' => $examinerId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 6. Roster for CSC401
        $rosterMatrics = [
            '220404001' => ['type' => 'regular', 'status' => 'submitted', 'in' => '13:05', 'sub' => '15:45'],
            '220404002' => ['type' => 'regular', 'status' => 'writing', 'in' => '13:12', 'sub' => null, 'assisted' => true],
            '220404007' => ['type' => 'regular', 'status' => 'writing', 'in' => '13:10', 'sub' => null],
            'CSC/2021/009' => ['type' => 'carryover', 'status' => 'absent', 'in' => null, 'sub' => null],
            '220404010' => ['type' => 'elective', 'status' => 'flagged', 'in' => '13:25', 'sub' => null],
        ];

        foreach ($rosterMatrics as $mat => $info) {
            DB::table('timetable_students')->updateOrInsert(
                ['timetable_id' => $tId, 'matric_no' => $mat],
                [
                    'candidate_type' => $info['type'],
                    'created_at' => now(),
                ]
            );

            // Attendance record if checked in
            if ($info['in']) {
                $status = $info['status'] === 'submitted' ? 'submitted' : 'checked_in';
                DB::table('attendance_records')->updateOrInsert(
                    ['timetable_id' => $tId, 'matric_no' => $mat],
                    [
                        'session_id' => 1,
                        'status' => $status,
                        'checkin_method' => !empty($info['assisted']) ? 'assisted' : 'qr_scan',
                        'checked_in_at' => Carbon::today()->setTimeFromTimeString($info['in']),
                        'entry_examiner_id' => $examinerId,
                        'submitted_at' => $info['sub'] ? Carbon::today()->setTimeFromTimeString($info['sub']) : null,
                        'assisted_reason' => !empty($info['assisted']) ? 'Camera focus failure' : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
