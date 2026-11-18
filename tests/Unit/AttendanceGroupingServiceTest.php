<?php

namespace Tests\Unit;

use App\Services\AttendanceGroupingService;
use App\Services\MatriculationNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceGroupingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AttendanceGroupingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AttendanceGroupingService::class);
    }

    public function test_groups_attendance_by_department_level_and_students(): void
    {
        // 1. Setup session
        $sessionId = DB::table('exam_sessions')->insertGetId([
            'semester'      => 'First Semester',
            'academic_year' => '2025/2026',
            'fee_amount'    => 100000,
            'aes_key'       => base64_encode(random_bytes(32)),
            'hmac_secret'   => base64_encode(random_bytes(32)),
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // 2. Setup department
        $deptId = DB::table('departments')->insertGetId([
            'dept_name'       => 'Computer Science',
            'department_code' => '04',
            'faculty_code'    => '04',
            'faculty'         => 'Faculty of Computing',
        ]);

        // 3. Setup official students (Registry is authoritative)
        // Note: 210404001 has admission year 2021, but level explicitly 400
        // 220404002 has admission year 2022, level explicitly 400
        // 250404003 has admission year 2025, level explicitly 100
        DB::table('official_students')->insert([
            [
                'matric_number'            => '210404001',
                'full_name'                => 'ALABI TAIWO CARRYOVER',
                'department'               => 'Computer Science',
                'level'                    => '400',
                'status'                   => 'active',
                'created_at'               => now(),
                'updated_at'               => now(),
                'admission_year'           => 2021,
                'department_code'          => '04',
                'faculty_code'             => '04',
                'normalized_matric_number' => '210404001',
            ],
            [
                'matric_number'            => '220404002',
                'full_name'                => 'BELLO AISHA REGULAR',
                'department'               => 'Computer Science',
                'level'                    => '400',
                'status'                   => 'active',
                'created_at'               => now(),
                'updated_at'               => now(),
                'admission_year'           => 2022,
                'department_code'          => '04',
                'faculty_code'             => '04',
                'normalized_matric_number' => '220404002',
            ],
            [
                'matric_number'            => '250404003',
                'full_name'                => 'CHUKWU EMEKA FRESH',
                'department'               => 'Computer Science',
                'level'                    => '100',
                'status'                   => 'active',
                'created_at'               => now(),
                'updated_at'               => now(),
                'admission_year'           => 2025,
                'department_code'          => '04',
                'faculty_code'             => '04',
                'normalized_matric_number' => '250404003',
            ],
            [
                'matric_number'            => '230405004',
                'full_name'                => 'DAVID SE SOFTWARE',
                'department'               => 'Software Engineering',
                'level'                    => '300',
                'status'                   => 'active',
                'created_at'               => now(),
                'updated_at'               => now(),
                'admission_year'           => 2023,
                'department_code'          => '05',
                'faculty_code'             => '04',
                'normalized_matric_number' => '230405004',
            ],
        ]);

        // 4. Setup timetable (CSC401)
        $ttId = DB::table('timetables')->insertGetId([
            'exam_session_id' => $sessionId,
            'department_id'   => $deptId,
            'level'           => '400',
            'course_code'     => 'CSC401',
            'course_title'    => 'Artificial Intelligence',
            'exam_date'       => '2026-11-20',
            'start_time'      => '09:00',
            'end_time'        => '12:00',
            'venue'           => 'Hall A',
            'status'          => 'scheduled',
            'assessment_type' => 'exam',
            'register_status' => 'confirmed',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // 5. Setup timetable roster
        DB::table('timetable_students')->insert([
            ['timetable_id' => $ttId, 'matric_no' => '210404001', 'candidate_type' => 'carryover', 'created_at' => now()],
            ['timetable_id' => $ttId, 'matric_no' => '220404002', 'candidate_type' => 'regular',   'created_at' => now()],
            ['timetable_id' => $ttId, 'matric_no' => '250404003', 'candidate_type' => 'regular',   'created_at' => now()],
        ]);

        // 6. Setup attendance records:
        // 210404001 has checked in and submitted
        // 220404002 has checked in (writing)
        // 250404003 has NOT checked in (yet to appear / absent)
        // 230405004 from Software Engineering did a walk-in check-in!
        DB::table('attendance_records')->insert([
            [
                'matric_no'         => '210404001',
                'timetable_id'      => $ttId,
                'session_id'        => $sessionId,
                'status'            => 'submitted',
                'checked_in_at'     => '2026-11-20 09:05:00',
                'submitted_at'      => '2026-11-20 11:45:00',
                'checkin_method'    => 'qr_scan',
                'assisted_reason'   => null,
                'assisted_notes'    => null,
                'entry_examiner_id' => null,
                'exit_examiner_id'  => null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'matric_no'         => '220404002',
                'timetable_id'      => $ttId,
                'session_id'        => $sessionId,
                'status'            => 'checked_in',
                'checked_in_at'     => '2026-11-20 09:12:00',
                'submitted_at'      => null,
                'checkin_method'    => 'assisted',
                'assisted_reason'   => 'Camera malfunction',
                'assisted_notes'    => 'Verified via student ID card',
                'entry_examiner_id' => null,
                'exit_examiner_id'  => null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'matric_no'         => '230405004',
                'timetable_id'      => $ttId,
                'session_id'        => $sessionId,
                'status'            => 'checked_in',
                'checked_in_at'     => '2026-11-20 09:20:00',
                'submitted_at'      => null,
                'checkin_method'    => 'qr_scan',
                'assisted_reason'   => null,
                'assisted_notes'    => null,
                'entry_examiner_id' => null,
                'exit_examiner_id'  => null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
        ]);

        $result = $this->service->getGroupedAttendance($sessionId, $ttId);
        $hierarchy = $result['hierarchy'];

        // Assert Department grouping
        $this->assertArrayHasKey('Computer Science', $hierarchy);
        $this->assertArrayHasKey('Software Engineering', $hierarchy);

        // Assert Level grouping inside Computer Science
        $csLevels = $hierarchy['Computer Science']['levels'];
        $this->assertArrayHasKey('400L', $csLevels);
        $this->assertArrayHasKey('100L', $csLevels);

        // Assert Level grouping inside Software Engineering
        $seLevels = $hierarchy['Software Engineering']['levels'];
        $this->assertArrayHasKey('300L', $seLevels);

        // Assert Students under Computer Science 400L
        $cs400Students = collect($csLevels['400L']['students']);
        $this->assertCount(2, $cs400Students);
        $carryover = $cs400Students->firstWhere('matric_no', '210404001');
        $this->assertNotNull($carryover);
        $this->assertSame('submitted', $carryover['status']);
        $this->assertSame('carryover', $carryover['candidate_type']);
        $this->assertSame('ALABI TAIWO CARRYOVER', $carryover['full_name']);

        $regular = $cs400Students->firstWhere('matric_no', '220404002');
        $this->assertNotNull($regular);
        $this->assertSame('checked_in', $regular['status']);
        $this->assertSame('assisted', $regular['checkin_method']);

        // Assert Student under Computer Science 100L
        $cs100Students = collect($csLevels['100L']['students']);
        $this->assertCount(1, $cs100Students);
        $absent = $cs100Students->firstWhere('matric_no', '250404003');
        $this->assertNotNull($absent);
        $this->assertSame('not_checked_in', $absent['status']);
        $this->assertSame('Yet to Appear', $absent['status_label']);

        // Assert Student under Software Engineering 300L (Walk-in)
        $se300Students = collect($seLevels['300L']['students']);
        $this->assertCount(1, $se300Students);
        $walkIn = $se300Students->firstWhere('matric_no', '230405004');
        $this->assertNotNull($walkIn);
        $this->assertSame('walk_in', $walkIn['candidate_type']);

        // Assert Summaries
        $summary = $result['overall_summary'];
        $this->assertSame(4, $summary['total_candidates']);
        $this->assertSame(3, $summary['total_present']);
        $this->assertSame(2, $summary['checked_in']);
        $this->assertSame(1, $summary['submitted']);
        $this->assertSame(1, $summary['yet_to_appear']);
    }

    public function test_groups_verification_logs_around_students_without_duplicates(): void
    {
        // Setup session
        $sessionId = DB::table('exam_sessions')->insertGetId([
            'semester'      => 'First Semester',
            'academic_year' => '2025/2026',
            'fee_amount'    => 100000,
            'aes_key'       => base64_encode(random_bytes(32)),
            'hmac_secret'   => base64_encode(random_bytes(32)),
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Setup department
        $deptId = DB::table('departments')->insertGetId([
            'dept_name'       => 'Computer Science',
            'department_code' => '04',
            'faculty_code'    => '04',
            'faculty'         => 'Faculty of Computing',
        ]);

        // Setup portal student (for foreign key on qr_tokens)
        DB::table('students')->insert([
            'matric_no'      => '220404001',
            'full_name'      => 'Ogundipe Babatunde',
            'department_id'  => $deptId,
            'session_id'     => $sessionId,
            'level'          => '400',
            'photo_path'     => 'photos/test.jpg',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Setup official student
        DB::table('official_students')->insert([
            'matric_number'            => '220404001',
            'full_name'                => 'OGUNDIPE BABATUNDE',
            'department'               => 'Computer Science',
            'level'                    => '400',
            'status'                   => 'active',
            'created_at'               => now(),
            'updated_at'               => now(),
            'admission_year'           => 2022,
            'department_code'          => '04',
            'faculty_code'             => '04',
            'normalized_matric_number' => '220404001',
        ]);

        // Setup examiner
        $examinerId = DB::table('examiners')->insertGetId([
            'username'      => 'examiner_test',
            'full_name'     => 'Examiner Test',
            'password_hash' => 'hash',
            'role'          => 'examiner',
            'is_active'     => 1,
            'created_at'    => now(),
        ]);

        // Setup qr_token for student
        DB::table('qr_tokens')->insert([
            'token_id'          => 'token-abc-123',
            'student_id'        => '220404001',
            'session_id'        => $sessionId,
            'encrypted_payload' => 'payload',
            'hmac_signature'    => 'sig',
            'status'            => 'used',
            'issued_at'         => now(),
            'used_at'           => now(),
        ]);

        // Setup 3 verification logs for the same student (entry scan, duplicate attempt, exit scan)
        DB::table('verification_logs')->insert([
            [
                'token_id'            => 'token-abc-123',
                'examiner_id'         => $examinerId,
                'decision'            => 'APPROVED',
                'timestamp'           => '2026-11-20 09:05:00',
                'device_fp'           => 'fp1',
                'ip_address'          => '127.0.0.1',
                'rejection_reason'    => null,
                'timetable_id'        => 1,
                'scan_classification' => 'regular',
            ],
            [
                'token_id'            => 'token-abc-123',
                'examiner_id'         => $examinerId,
                'decision'            => 'DUPLICATE',
                'timestamp'           => '2026-11-20 09:10:00',
                'device_fp'           => 'fp1',
                'ip_address'          => '127.0.0.1',
                'rejection_reason'    => 'Pass already scanned for entry',
                'timetable_id'        => 1,
                'scan_classification' => 'duplicate',
            ],
            [
                'token_id'            => 'token-abc-123',
                'examiner_id'         => $examinerId,
                'decision'            => 'APPROVED',
                'timestamp'           => '2026-11-20 11:30:00',
                'device_fp'           => 'fp1',
                'ip_address'          => '127.0.0.1',
                'rejection_reason'    => null,
                'timetable_id'        => 1,
                'scan_classification' => 'departure',
            ],
        ]);

        $result = $this->service->getGroupedVerificationLogs();
        $hierarchy = $result['hierarchy'];

        $this->assertArrayHasKey('Computer Science', $hierarchy);
        $cs = $hierarchy['Computer Science'];
        $this->assertArrayHasKey('400L', $cs['levels']);

        // Exactly ONE student card for 220404001, not 3 separate student rows!
        $students = $cs['levels']['400L']['students'];
        $this->assertCount(1, $students);

        $student = $students[0];
        $this->assertSame('220404001', $student['matric_no']);
        $this->assertSame('OGUNDIPE BABATUNDE', $student['full_name']);
        $this->assertSame(3, $student['total_scans']);
        $this->assertSame(2, $student['approved_count']);
        $this->assertSame(1, $student['repeated_count']);
        $this->assertCount(3, $student['scans']);
    }

    public function test_handles_unmapped_registry_records_safely(): void
    {
        // Attendance record for student not in official_students
        DB::table('attendance_records')->insert([
            'matric_no'         => '999999999',
            'timetable_id'      => 10,
            'session_id'        => 1,
            'status'            => 'checked_in',
            'checked_in_at'     => '2026-11-20 10:00:00',
            'submitted_at'      => null,
            'checkin_method'    => 'qr_scan',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $result = $this->service->getGroupedAttendance(1);
        $hierarchy = $result['hierarchy'];

        $this->assertArrayHasKey('Unmapped / External Registry', $hierarchy);
        $unmappedDept = $hierarchy['Unmapped / External Registry'];
        $this->assertArrayHasKey('UNASSIGNED', $unmappedDept['levels']);

        $student = $unmappedDept['levels']['UNASSIGNED']['students'][0];
        $this->assertSame('999999999', $student['matric_no']);
        $this->assertFalse($student['is_registry_verified']);
        $this->assertNull($student['level']);
    }

    public function test_does_not_infer_level_from_admission_year_prefix(): void
    {
        // 2 students with different admission years: 2020 (prefix 20) and 2023 (prefix 23)
        // Both are officially recorded in the registry as Level 300
        DB::table('official_students')->insert([
            [
                'matric_number'            => '200404001',
                'full_name'                => 'SENIOR CARRYOVER STUDENT',
                'department'               => 'Computer Science',
                'level'                    => '300', // Explicitly 300L
                'status'                   => 'active',
                'created_at'               => now(),
                'updated_at'               => now(),
                'admission_year'           => 2020,
                'department_code'          => '04',
                'faculty_code'             => '04',
                'normalized_matric_number' => '200404001',
            ],
            [
                'matric_number'            => '230404002',
                'full_name'                => 'REGULAR THIRD YEAR',
                'department'               => 'Computer Science',
                'level'                    => '300', // Explicitly 300L
                'status'                   => 'active',
                'created_at'               => now(),
                'updated_at'               => now(),
                'admission_year'           => 2023,
                'department_code'          => '04',
                'faculty_code'             => '04',
                'normalized_matric_number' => '230404002',
            ],
        ]);

        DB::table('attendance_records')->insert([
            [
                'matric_no'         => '200404001',
                'timetable_id'      => 5,
                'session_id'        => 1,
                'status'            => 'checked_in',
                'checked_in_at'     => '2026-11-20 09:00:00',
                'submitted_at'      => null,
                'checkin_method'    => 'qr_scan',
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'matric_no'         => '230404002',
                'timetable_id'      => 5,
                'session_id'        => 1,
                'status'            => 'submitted',
                'checked_in_at'     => '2026-11-20 09:05:00',
                'submitted_at'      => '2026-11-20 11:30:00',
                'checkin_method'    => 'qr_scan',
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
        ]);

        $result = $this->service->getGroupedAttendance(1);
        $csLevels = $result['hierarchy']['Computer Science']['levels'];

        // Both must be grouped under 300L!
        $this->assertArrayHasKey('300L', $csLevels);
        $this->assertArrayNotHasKey('500L', $csLevels);
        $this->assertArrayNotHasKey('600L', $csLevels);

        $students = $csLevels['300L']['students'];
        $this->assertCount(2, $students);
    }

    public function test_filters_by_search_query_status_and_department(): void
    {
        DB::table('official_students')->insert([
            [
                'matric_number'            => '220404011',
                'full_name'                => 'AMINA MUSA',
                'department'               => 'Computer Science',
                'level'                    => '400',
                'status'                   => 'active',
                'created_at'               => now(),
                'updated_at'               => now(),
                'admission_year'           => 2022,
                'department_code'          => '04',
                'faculty_code'             => '04',
                'normalized_matric_number' => '220404011',
            ],
            [
                'matric_number'            => '220405012',
                'full_name'                => 'CHINEDU OKAFOR',
                'department'               => 'Software Engineering',
                'level'                    => '400',
                'status'                   => 'active',
                'created_at'               => now(),
                'updated_at'               => now(),
                'admission_year'           => 2022,
                'department_code'          => '05',
                'faculty_code'             => '04',
                'normalized_matric_number' => '220405012',
            ],
        ]);

        DB::table('attendance_records')->insert([
            [
                'matric_no'         => '220404011',
                'timetable_id'      => 8,
                'session_id'        => 2,
                'status'            => 'checked_in',
                'checked_in_at'     => '2026-11-20 09:00:00',
                'submitted_at'      => null,
                'checkin_method'    => 'qr_scan',
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'matric_no'         => '220405012',
                'timetable_id'      => 8,
                'session_id'        => 2,
                'status'            => 'submitted',
                'checked_in_at'     => '2026-11-20 09:05:00',
                'submitted_at'      => '2026-11-20 11:30:00',
                'checkin_method'    => 'qr_scan',
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
        ]);

        // Filter by search: 'AMINA'
        $searchRes = $this->service->getGroupedAttendance(2, null, ['q' => 'AMINA']);
        $this->assertSame(1, $searchRes['total_students_count']);
        $this->assertArrayHasKey('Computer Science', $searchRes['hierarchy']);
        $this->assertArrayNotHasKey('Software Engineering', $searchRes['hierarchy']);

        // Filter by status: 'submitted'
        $statusRes = $this->service->getGroupedAttendance(2, null, ['status' => 'submitted']);
        $this->assertSame(1, $statusRes['total_students_count']);
        $this->assertArrayHasKey('Software Engineering', $statusRes['hierarchy']);
        $this->assertArrayNotHasKey('Computer Science', $statusRes['hierarchy']);

        // Filter by department: 'Computer Science'
        $deptRes = $this->service->getGroupedAttendance(2, null, ['department' => 'Computer Science']);
        $this->assertSame(1, $deptRes['total_students_count']);
        $this->assertArrayHasKey('Computer Science', $deptRes['hierarchy']);
    }
}
