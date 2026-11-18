<?php

namespace Tests\Feature;

use App\Models\Examiner;
use App\Models\OfficialStudent;
use App\Services\AttendanceGroupingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceGroupingAndMatriculationOrganisationTest extends TestCase
{
    use RefreshDatabase;

    protected int $sessionId;
    protected int $csDeptId;
    protected int $seDeptId;
    protected int $examinerId;
    protected int $timetableId;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Exam session
        $this->sessionId = DB::table('exam_sessions')->insertGetId([
            'semester'      => 'First Semester',
            'academic_year' => '2025/2026',
            'fee_amount'    => 100000,
            'aes_key'       => base64_encode(random_bytes(32)),
            'hmac_secret'   => base64_encode(random_bytes(32)),
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // 2. Departments
        $this->csDeptId = DB::table('departments')->insertGetId([
            'dept_name'       => 'Computer Science',
            'department_code' => '04',
            'faculty_code'    => '04',
            'faculty'         => 'Faculty of Computing',
        ]);

        $this->seDeptId = DB::table('departments')->insertGetId([
            'dept_name'       => 'Software Engineering',
            'department_code' => '05',
            'faculty_code'    => '04',
            'faculty'         => 'Faculty of Computing',
        ]);

        // 3. Admin/Examiner
        $this->examinerId = DB::table('examiners')->insertGetId([
            'username'      => 'admin_user',
            'full_name'     => 'Chief Invigilator Admin',
            'password_hash' => bcrypt('password123'),
            'role'          => 'admin',
            'is_active'     => 1,
            'created_at'    => now(),
        ]);

        // 4. Official Student Registry
        DB::table('official_students')->insert([
            // 400L Computer Science Regular
            [
                'matric_number'            => '220404001',
                'full_name'                => 'OGUNDIPE BABATUNDE OLUWASEGUN',
                'department'               => 'Computer Science',
                'level'                    => '400',
                'status'                   => 'active',
                'admission_year'           => 2022,
                'department_code'          => '04',
                'faculty_code'             => '04',
                'normalized_matric_number' => '220404001',
                'created_at'               => now(),
                'updated_at'               => now(),
            ],
            // 400L Computer Science Carry-over (Admission year 2020, but level is explicitly 400)
            [
                'matric_number'            => '200404002',
                'full_name'                => 'ADEYEMI OLUWASEUN SENIOR',
                'department'               => 'Computer Science',
                'level'                    => '400',
                'status'                   => 'active',
                'admission_year'           => 2020,
                'department_code'          => '04',
                'faculty_code'             => '04',
                'normalized_matric_number' => '200404002',
                'created_at'               => now(),
                'updated_at'               => now(),
            ],
            // 100L Computer Science Fresh
            [
                'matric_number'            => '250404003',
                'full_name'                => 'OKAFOR CHUKWUDI FRESHMAN',
                'department'               => 'Computer Science',
                'level'                    => '100',
                'status'                   => 'active',
                'admission_year'           => 2025,
                'department_code'          => '04',
                'faculty_code'             => '04',
                'normalized_matric_number' => '250404003',
                'created_at'               => now(),
                'updated_at'               => now(),
            ],
            // 300L Software Engineering
            [
                'matric_number'            => '230405004',
                'full_name'                => 'BELLO MARGARET SOFTWARE',
                'department'               => 'Software Engineering',
                'level'                    => '300',
                'status'                   => 'active',
                'admission_year'           => 2023,
                'department_code'          => '05',
                'faculty_code'             => '04',
                'normalized_matric_number' => '230405004',
                'created_at'               => now(),
                'updated_at'               => now(),
            ],
        ]);

        // Portal accounts for photos
        DB::table('students')->insert([
            [
                'matric_no'          => '220404001',
                'full_name'          => 'Ogundipe Babatunde',
                'department_id'      => $this->csDeptId,
                'session_id'         => $this->sessionId,
                'level'              => '400',
                'photo_path'         => 'photos/220404001.jpg',
                'profile_photo_path' => 'profile_photos/220404001.jpg',
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'matric_no'          => '200404002',
                'full_name'          => 'Adeyemi Oluwaseun',
                'department_id'      => $this->csDeptId,
                'session_id'         => $this->sessionId,
                'level'              => '400',
                'photo_path'         => 'photos/200404002.jpg',
                'profile_photo_path' => null,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
        ]);

        // 5. Timetable (CSC401)
        $this->timetableId = DB::table('timetables')->insertGetId([
            'exam_session_id' => $this->sessionId,
            'department_id'   => $this->csDeptId,
            'level'           => '400',
            'course_code'     => 'CSC401',
            'course_title'    => 'Artificial Intelligence & Expert Systems',
            'exam_date'       => '2026-11-20',
            'start_time'      => '09:00',
            'end_time'        => '12:00',
            'venue'           => 'CBT Lab 1',
            'status'          => 'scheduled',
            'assessment_type' => 'exam',
            'examiner_id'     => $this->examinerId,
            'register_status' => 'confirmed',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // 6. Confirmed roster in timetable_students
        DB::table('timetable_students')->insert([
            ['timetable_id' => $this->timetableId, 'matric_no' => '220404001', 'candidate_type' => 'regular',   'created_at' => now()],
            ['timetable_id' => $this->timetableId, 'matric_no' => '200404002', 'candidate_type' => 'carryover', 'created_at' => now()],
            ['timetable_id' => $this->timetableId, 'matric_no' => '250404003', 'candidate_type' => 'regular',   'created_at' => now()],
        ]);
    }

    protected function actingAsAdmin(): self
    {
        return $this->withSession([
            'examiner_id'       => $this->examinerId,
            'examiner_username' => 'admin_user',
            'examiner_name'     => 'Chief Invigilator Admin',
            'examiner_role'     => 'admin',
        ]);
    }

    public function test_attendance_page_renders_department_level_students_hierarchy(): void
    {
        // 220404001: Submitted
        // 200404002: Checked In (Assisted)
        // 250404003: Yet to appear (no attendance record)
        DB::table('attendance_records')->insert([
            [
                'matric_no'         => '220404001',
                'timetable_id'      => $this->timetableId,
                'session_id'        => $this->sessionId,
                'status'            => 'submitted',
                'checked_in_at'     => '2026-11-20 09:05:00',
                'submitted_at'      => '2026-11-20 11:45:00',
                'checkin_method'    => 'qr_scan',
                'assisted_reason'   => null,
                'assisted_notes'    => null,
                'entry_examiner_id' => $this->examinerId,
                'exit_examiner_id'  => $this->examinerId,
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'matric_no'         => '200404002',
                'timetable_id'      => $this->timetableId,
                'session_id'        => $this->sessionId,
                'status'            => 'checked_in',
                'checked_in_at'     => '2026-11-20 09:12:00',
                'submitted_at'      => null,
                'checkin_method'    => 'assisted',
                'assisted_reason'   => 'Damaged screen glass',
                'assisted_notes'    => 'Identity confirmed with national ID',
                'entry_examiner_id' => $this->examinerId,
                'exit_examiner_id'  => null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
        ]);

        $response = $this->actingAsAdmin()->get(route('admin.attendance', [
            'session_id'   => $this->sessionId,
            'timetable_id' => $this->timetableId,
        ]));

        $response->assertOk();

        // 1. Department header
        $response->assertSee('Computer Science');

        // 2. Academic Level headers
        $response->assertSee('400 Level');
        $response->assertSee('100 Level');

        // 3. Authoritative full names
        $response->assertSee('OGUNDIPE BABATUNDE OLUWASEGUN');
        $response->assertSee('ADEYEMI OLUWASEUN SENIOR');
        $response->assertSee('OKAFOR CHUKWUDI FRESHMAN');

        // 4. Matriculation numbers
        $response->assertSee('220404001');
        $response->assertSee('200404002');
        $response->assertSee('250404003');

        // 5. Candidate types
        $response->assertSee('Carryover');
        $response->assertSee('Regular');

        // 6. Statuses
        $response->assertSee('Submitted');
        $response->assertSee('Checked In');
        $response->assertSee('Yet to Appear');

        // 7. Assisted checkin reason
        $response->assertSee('Damaged screen glass');
    }

    public function test_carryover_student_grouped_by_registry_level_not_admission_year(): void
    {
        // 200404002 has admission year 2020 (prefix 20), but registry level is explicitly 400.
        // It must appear under 400 Level, NEVER 600 Level or 500 Level!
        DB::table('attendance_records')->insert([
            'matric_no'         => '200404002',
            'timetable_id'      => $this->timetableId,
            'session_id'        => $this->sessionId,
            'status'            => 'checked_in',
            'checked_in_at'     => '2026-11-20 09:12:00',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $response = $this->actingAsAdmin()->get(route('admin.attendance', [
            'session_id'   => $this->sessionId,
            'timetable_id' => $this->timetableId,
        ]));

        $response->assertOk();
        $response->assertSee('400 Level');
        $response->assertDontSee('600 Level');
    }

    public function test_unmapped_registry_records_appear_under_unmapped_department(): void
    {
        // An external or unmapped matric
        DB::table('attendance_records')->insert([
            'matric_no'         => '990404999',
            'timetable_id'      => $this->timetableId,
            'session_id'        => $this->sessionId,
            'status'            => 'checked_in',
            'checked_in_at'     => '2026-11-20 09:30:00',
            'checkin_method'    => 'qr_scan',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $response = $this->actingAsAdmin()->get(route('admin.attendance', [
            'session_id'   => $this->sessionId,
            'timetable_id' => $this->timetableId,
        ]));

        $response->assertOk();
        $response->assertSee('Unmapped / External Registry');
        $response->assertSee('Unassigned Level');
        $response->assertSee('990404999');
    }

    public function test_verification_logs_page_groups_scans_around_students(): void
    {
        // Setup qr_token for student 220404001
        DB::table('qr_tokens')->insert([
            'token_id'          => 'tok-multiscan-001',
            'student_id'        => '220404001',
            'session_id'        => $this->sessionId,
            'encrypted_payload' => 'payload',
            'hmac_signature'    => 'sig',
            'status'            => 'used',
            'issued_at'         => now(),
            'used_at'           => now(),
        ]);

        // Insert 3 scans for student 220404001:
        // 1. Initial entry APPROVED
        // 2. Repeated scan DUPLICATE
        // 3. Exit departure scan APPROVED
        DB::table('verification_logs')->insert([
            [
                'token_id'            => 'tok-multiscan-001',
                'examiner_id'         => $this->examinerId,
                'decision'            => 'APPROVED',
                'timestamp'           => '2026-11-20 09:02:00',
                'device_fp'           => 'device_cam_1',
                'ip_address'          => '192.168.1.10',
                'rejection_reason'    => null,
                'timetable_id'        => $this->timetableId,
                'scan_classification' => 'regular',
            ],
            [
                'token_id'            => 'tok-multiscan-001',
                'examiner_id'         => $this->examinerId,
                'decision'            => 'DUPLICATE',
                'timestamp'           => '2026-11-20 09:08:00',
                'device_fp'           => 'device_cam_1',
                'ip_address'          => '192.168.1.10',
                'rejection_reason'    => 'Duplicate pass scanned',
                'timetable_id'        => $this->timetableId,
                'scan_classification' => 'duplicate',
            ],
            [
                'token_id'            => 'tok-multiscan-001',
                'examiner_id'         => $this->examinerId,
                'decision'            => 'APPROVED',
                'timestamp'           => '2026-11-20 11:45:00',
                'device_fp'           => 'device_cam_1',
                'ip_address'          => '192.168.1.10',
                'rejection_reason'    => null,
                'timetable_id'        => $this->timetableId,
                'scan_classification' => 'departure',
            ],
        ]);

        // 1. Test Institutional Hierarchy mode (Default)
        $response = $this->actingAsAdmin()->get(route('admin.scan-logs'));
        $response->assertOk();

        // Must show hierarchy
        $response->assertSee('Computer Science');
        $response->assertSee('400 Level');
        $response->assertSee('OGUNDIPE BABATUNDE OLUWASEGUN');
        $response->assertSee('220404001');
        $response->assertSee('3 scans recorded');
        $response->assertSee('Departure');
        $response->assertSee('Duplicate pass scanned');

        // 2. Test Chronological Flat Feed mode (?view=flat)
        $flatResponse = $this->actingAsAdmin()->get(route('admin.scan-logs', ['view' => 'flat']));
        $flatResponse->assertOk();
        $flatResponse->assertSee('Chronological Feed');
        $flatResponse->assertSee('220404001');
    }

    public function test_attendance_search_filters_students_correctly(): void
    {
        DB::table('attendance_records')->insert([
            [
                'matric_no'         => '220404001',
                'timetable_id'      => $this->timetableId,
                'session_id'        => $this->sessionId,
                'status'            => 'submitted',
                'checked_in_at'     => '2026-11-20 09:05:00',
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'matric_no'         => '200404002',
                'timetable_id'      => $this->timetableId,
                'session_id'        => $this->sessionId,
                'status'            => 'checked_in',
                'checked_in_at'     => '2026-11-20 09:12:00',
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
        ]);

        // Search for 'OGUNDIPE'
        $resp = $this->actingAsAdmin()->get(route('admin.attendance', [
            'session_id'   => $this->sessionId,
            'timetable_id' => $this->timetableId,
            'q'            => 'OGUNDIPE',
        ]));

        $resp->assertOk();
        $resp->assertSee('OGUNDIPE BABATUNDE OLUWASEGUN');
        $resp->assertDontSee('ADEYEMI OLUWASEUN SENIOR');
    }
}
