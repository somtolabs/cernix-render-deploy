<?php

namespace Tests\Feature;

use App\Services\CryptoService;
use App\Services\ExamPassService;
use App\Services\QrTokenService;
use App\Services\RemitaService;
use App\Services\VerificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssessmentEligibilityAndRosterVerificationTest extends TestCase
{
    use RefreshDatabase;

    private VerificationService $verificationService;
    private CryptoService $cryptoService;
    private ExamPassService $examPassService;

    private int $sessionId;
    private int $examinerId;
    private int $csDeptId;
    private int $mathDeptId;
    private string $aesKey;
    private string $hmacSecret;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(Carbon::today('Africa/Lagos')->toDateString() . ' 09:30:00', 'Africa/Lagos'));

        $this->cryptoService = new CryptoService();
        $this->verificationService = new VerificationService($this->cryptoService);
        $this->examPassService = new ExamPassService(
            $this->createMock(RemitaService::class),
            new QrTokenService($this->cryptoService)
        );

        $this->csDeptId = DB::table('departments')->insertGetId([
            'dept_name' => 'Computer Science',
            'faculty' => 'Faculty of Computing',
            'department_code' => '04',
            'faculty_code' => '04',
        ], 'dept_id');

        $this->mathDeptId = DB::table('departments')->insertGetId([
            'dept_name' => 'Mathematics',
            'faculty' => 'Faculty of Science',
            'department_code' => '09',
            'faculty_code' => '09',
        ], 'dept_id');

        $this->aesKey = bin2hex(random_bytes(32));
        $this->hmacSecret = bin2hex(random_bytes(32));

        $this->sessionId = DB::table('exam_sessions')->insertGetId([
            'semester' => 'First Semester',
            'academic_year' => '2025/2026',
            'fee_amount' => 10000.00,
            'aes_key' => $this->aesKey,
            'hmac_secret' => $this->hmacSecret,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->examinerId = DB::table('examiners')->insertGetId([
            'username' => 'examiner_roster_test',
            'full_name' => 'Chief Invigilator',
            'password_hash' => bcrypt('password123'),
            'role' => 'examiner',
            'is_active' => true,
            'created_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Scenario 1: A registered student on a confirmed register is approved.
     */
    public function test_scenario_01_registered_student_on_confirmed_register_is_approved(): void
    {
        $matricNo = 'CSC/2021/001';
        $this->createOfficialStudent($matricNo, 'Adebayo Emmanuel', 'Computer Science', '400');
        $this->createRegisteredStudent($matricNo, $this->csDeptId, '400');

        $timetableId = $this->createTimetable('CSC401', $this->csDeptId, '400', 'confirmed');
        $this->enrollStudentOnTimetable($timetableId, $matricNo);

        $qrData = $this->createCourseQrData($matricNo, $timetableId);

        $result = $this->verificationService->verifyQr($qrData, $this->examinerId, 'test-device', '127.0.0.1', $timetableId);

        $this->assertSame('APPROVED', $result['status']);
        $this->assertNotNull($result['student']);
        $this->assertSame($matricNo, $result['student']['matric_no']);
    }

    /**
     * Scenario 2: An unregistered student from the correct department and level is rejected.
     */
    public function test_scenario_02_unregistered_student_from_same_cohort_is_rejected(): void
    {
        $enrolledMatric = 'CSC/2021/001';
        $unregisteredCohortMatric = 'CSC/2021/002';

        $this->createOfficialStudent($enrolledMatric, 'Enrolled Student', 'Computer Science', '400');
        $this->createRegisteredStudent($enrolledMatric, $this->csDeptId, '400');

        $this->createOfficialStudent($unregisteredCohortMatric, 'Unregistered Cohort Student', 'Computer Science', '400');
        $this->createRegisteredStudent($unregisteredCohortMatric, $this->csDeptId, '400');

        $timetableId = $this->createTimetable('CSC401', $this->csDeptId, '400', 'confirmed');
        // Only enroll CSC/2021/001
        $this->enrollStudentOnTimetable($timetableId, $enrolledMatric);

        $qrData = $this->createCourseQrData($unregisteredCohortMatric, $timetableId);

        $result = $this->verificationService->verifyQr($qrData, $this->examinerId, 'test-device', '127.0.0.1', $timetableId);

        $this->assertSame('REJECTED', $result['status']);
        $this->assertSame('student_not_on_register', $result['reason']);
        $this->assertNull($result['student']);
    }

    /**
     * Scenario 3: A student from another level is approved when explicitly registered (elective / out-of-level).
     */
    public function test_scenario_03_student_from_another_level_is_approved_when_explicitly_registered(): void
    {
        $outOfLevelMatric = 'CSC/2022/301'; // 300 level student taking 400 level course
        $this->createOfficialStudent($outOfLevelMatric, 'Advanced Level Student', 'Computer Science', '300');
        $this->createRegisteredStudent($outOfLevelMatric, $this->csDeptId, '300');

        $timetableId = $this->createTimetable('CSC401', $this->csDeptId, '400', 'confirmed');
        $this->enrollStudentOnTimetable($timetableId, $outOfLevelMatric, 'elective');

        $qrData = $this->createCourseQrData($outOfLevelMatric, $timetableId);

        $result = $this->verificationService->verifyQr($qrData, $this->examinerId, 'test-device', '127.0.0.1', $timetableId);

        $this->assertSame('APPROVED', $result['status']);
        $this->assertSame($outOfLevelMatric, $result['student']['matric_no']);
    }

    /**
     * Scenario 4: A carry-over student is approved when explicitly registered.
     */
    public function test_scenario_04_carry_over_student_is_approved_when_explicitly_registered(): void
    {
        $carryOverMatric = 'CSC/2020/501'; // 500 level student retaking 400 level course
        $this->createOfficialStudent($carryOverMatric, 'Carryover Candidate', 'Computer Science', '500');
        $this->createRegisteredStudent($carryOverMatric, $this->csDeptId, '500');

        $timetableId = $this->createTimetable('CSC401', $this->csDeptId, '400', 'confirmed');
        $this->enrollStudentOnTimetable($timetableId, $carryOverMatric, 'carry_over');

        $qrData = $this->createCourseQrData($carryOverMatric, $timetableId);

        $result = $this->verificationService->verifyQr($qrData, $this->examinerId, 'test-device', '127.0.0.1', $timetableId);

        $this->assertSame('APPROVED', $result['status']);
        $this->assertSame($carryOverMatric, $result['student']['matric_no']);
    }

    /**
     * Scenario 5: An assessment with an empty register is rejected.
     */
    public function test_scenario_05_assessment_with_empty_register_is_rejected(): void
    {
        $matricNo = 'CSC/2021/001';
        $this->createOfficialStudent($matricNo, 'Adebayo Emmanuel', 'Computer Science', '400');
        $this->createRegisteredStudent($matricNo, $this->csDeptId, '400');

        // Timetable marked confirmed, but zero candidates enrolled on timetable_students
        $timetableId = $this->createTimetable('CSC401', $this->csDeptId, '400', 'confirmed');

        $qrData = $this->createCourseQrData($matricNo, $timetableId);

        $result = $this->verificationService->verifyQr($qrData, $this->examinerId, 'test-device', '127.0.0.1', $timetableId);

        $this->assertSame('REJECTED', $result['status']);
        $this->assertSame('register_incomplete', $result['reason']);
    }

    /**
     * Scenario 6: A legacy assessment with an incomplete register is blocked.
     */
    public function test_scenario_06_legacy_assessment_with_incomplete_register_is_blocked(): void
    {
        $matricNo = 'CSC/2021/001';
        $this->createOfficialStudent($matricNo, 'Adebayo Emmanuel', 'Computer Science', '400');
        $this->createRegisteredStudent($matricNo, $this->csDeptId, '400');

        // Legacy timetable with default 'incomplete' register_status
        $timetableId = $this->createTimetable('CSC401', $this->csDeptId, '400', 'incomplete');
        $this->enrollStudentOnTimetable($timetableId, $matricNo);

        $qrData = $this->createCourseQrData($matricNo, $timetableId);

        $result = $this->verificationService->verifyQr($qrData, $this->examinerId, 'test-device', '127.0.0.1', $timetableId);

        $this->assertSame('REJECTED', $result['status']);
        $this->assertSame('register_incomplete', $result['reason']);
    }

    /**
     * Scenario 7: A student with a similar but different matriculation number is rejected.
     */
    public function test_scenario_07_similar_matriculation_number_is_rejected(): void
    {
        $authorizedMatric = 'CSC/2021/001';
        $similarMatric    = 'CSC/2021/010';

        $this->createOfficialStudent($authorizedMatric, 'Authorized Student', 'Computer Science', '400');
        $this->createRegisteredStudent($authorizedMatric, $this->csDeptId, '400');

        $this->createOfficialStudent($similarMatric, 'Similar Matric Student', 'Computer Science', '400');
        $this->createRegisteredStudent($similarMatric, $this->csDeptId, '400');

        $timetableId = $this->createTimetable('CSC401', $this->csDeptId, '400', 'confirmed');
        $this->enrollStudentOnTimetable($timetableId, $authorizedMatric);

        // Attempt verification with the similar matric
        $qrData = $this->createCourseQrData($similarMatric, $timetableId);

        $result = $this->verificationService->verifyQr($qrData, $this->examinerId, 'test-device', '127.0.0.1', $timetableId);

        $this->assertSame('REJECTED', $result['status']);
        $this->assertSame('student_not_on_register', $result['reason']);
    }

    /**
     * Scenario 8: A student absent from the official student registry is rejected.
     */
    public function test_scenario_08_student_absent_from_official_registry_is_rejected(): void
    {
        $bogusMatric = 'BOGUS/2021/999';

        // Student exists in local students table and timetable_students, but NOT in official_students
        $this->createRegisteredStudent($bogusMatric, $this->csDeptId, '400');
        $timetableId = $this->createTimetable('CSC401', $this->csDeptId, '400', 'confirmed');
        $this->enrollStudentOnTimetable($timetableId, $bogusMatric);

        $qrData = $this->createCourseQrData($bogusMatric, $timetableId);

        $result = $this->verificationService->verifyQr($qrData, $this->examinerId, 'test-device', '127.0.0.1', $timetableId);

        $this->assertSame('REJECTED', $result['status']);
        $this->assertSame('student_not_in_registry', $result['reason']);
    }

    /**
     * Scenario 9: QR verification and examination pass generation enforce the same eligibility rules.
     */
    public function test_scenario_09_exam_pass_generation_and_qr_verification_enforce_identical_rules(): void
    {
        $enrolledMatric = 'CSC/2021/001';
        $unenrolledMatric = 'CSC/2021/002';

        $this->createOfficialStudent($enrolledMatric, 'Enrolled Student', 'Computer Science', '400');
        $this->createRegisteredStudent($enrolledMatric, $this->csDeptId, '400');

        $this->createOfficialStudent($unenrolledMatric, 'Unenrolled Student', 'Computer Science', '400');
        $this->createRegisteredStudent($unenrolledMatric, $this->csDeptId, '400');

        $incompleteTimetable = $this->createTimetable('CSC401', $this->csDeptId, '400', 'incomplete');
        $confirmedTimetable  = $this->createTimetable('CSC402', $this->csDeptId, '400', 'confirmed');
        $this->enrollStudentOnTimetable($confirmedTimetable, $enrolledMatric);

        // Rule A: Incomplete register blocks pass generation
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Assessment register is incomplete and requires administrative review. Examination pass cannot be generated.');
        $this->examPassService->generate($enrolledMatric, $this->sessionId, $incompleteTimetable, 'TEST-DEMO', 10000);
    }

    /**
     * Scenario 9 (Part B): Unregistered student on confirmed timetable is blocked from pass generation.
     */
    public function test_scenario_09_part_b_unenrolled_student_blocked_from_pass_generation(): void
    {
        $enrolledMatric = 'CSC/2021/001';
        $unenrolledMatric = 'CSC/2021/002';

        $this->createOfficialStudent($enrolledMatric, 'Enrolled Student', 'Computer Science', '400');
        $this->createRegisteredStudent($enrolledMatric, $this->csDeptId, '400');

        $this->createOfficialStudent($unenrolledMatric, 'Unenrolled Student', 'Computer Science', '400');
        $this->createRegisteredStudent($unenrolledMatric, $this->csDeptId, '400');

        $confirmedTimetable = $this->createTimetable('CSC402', $this->csDeptId, '400', 'confirmed');
        $this->enrollStudentOnTimetable($confirmedTimetable, $enrolledMatric);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You are not registered on the confirmed student register for this assessment.');
        $this->examPassService->generate($unenrolledMatric, $this->sessionId, $confirmedTimetable, 'TEST-DEMO', 10000);
    }

    /**
     * Scenario 10: Assisted check-in succeeds for an eligible student without pretending to be a QR scan.
     */
    public function test_scenario_10_assisted_checkin_succeeds_without_fake_qr_tokens(): void
    {
        $matricNo = 'CSC/2021/001';
        $this->createOfficialStudent($matricNo, 'Assisted Student', 'Computer Science', '400');
        $this->createRegisteredStudent($matricNo, $this->csDeptId, '400');

        $timetableId = $this->createTimetable('CSC401', $this->csDeptId, '400', 'confirmed');
        $this->enrollStudentOnTimetable($timetableId, $matricNo);

        $examiner = DB::table('examiners')->where('examiner_id', $this->examinerId)->first();

        // 1. Lookup student
        $lookupResponse = $this->withSession($this->examinerSession($examiner))
            ->postJson(route('examiner.assessments.assisted-checkin.lookup', ['id' => $timetableId]), [
                'matric_no' => $matricNo,
            ]);

        $lookupResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('matric_no', $matricNo)
            ->assertJsonPath('already_checked_in', false);

        // 2. Process assisted check-in
        $processResponse = $this->withSession($this->examinerSession($examiner))
            ->postJson(route('examiner.assessments.assisted-checkin.process', ['id' => $timetableId]), [
                'matric_no' => $matricNo,
                'reason' => 'qr_damaged',
                'notes' => 'Candidate screen is cracked and unreadable.',
            ]);

        $processResponse->assertOk()
            ->assertJsonPath('success', true);

        // Verify attendance record has token_id NULL and checkin_method 'assisted'
        $this->assertDatabaseHas('attendance_records', [
            'matric_no' => $matricNo,
            'timetable_id' => $timetableId,
            'token_id' => null,
            'checkin_method' => 'assisted',
            'assisted_reason' => 'qr_damaged',
            'assisted_notes' => 'Candidate screen is cracked and unreadable.',
            'status' => 'checked_in',
        ]);

        // Zero rows in qr_tokens should be created or consumed
        $this->assertDatabaseMissing('qr_tokens', [
            'student_id' => $matricNo,
            'timetable_id' => $timetableId,
        ]);
    }

    /**
     * Scenario 11: Assisted check-in is rejected for a student absent from the confirmed register.
     */
    public function test_scenario_11_assisted_checkin_is_rejected_for_student_not_on_confirmed_register(): void
    {
        $enrolledMatric = 'CSC/2021/001';
        $unregisteredMatric = 'CSC/2021/088';

        $this->createOfficialStudent($enrolledMatric, 'Enrolled Student', 'Computer Science', '400');
        $this->createRegisteredStudent($enrolledMatric, $this->csDeptId, '400');

        $this->createOfficialStudent($unregisteredMatric, 'Unregistered Student', 'Computer Science', '400');
        $this->createRegisteredStudent($unregisteredMatric, $this->csDeptId, '400');

        $timetableId = $this->createTimetable('CSC401', $this->csDeptId, '400', 'confirmed');
        $this->enrollStudentOnTimetable($timetableId, $enrolledMatric);

        $examiner = DB::table('examiners')->where('examiner_id', $this->examinerId)->first();

        // 1. Lookup fails
        $lookupResponse = $this->withSession($this->examinerSession($examiner))
            ->postJson(route('examiner.assessments.assisted-checkin.lookup', ['id' => $timetableId]), [
                'matric_no' => $unregisteredMatric,
            ]);

        $lookupResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('reason', 'student_not_on_register');

        // 2. Process fails
        $processResponse = $this->withSession($this->examinerSession($examiner))
            ->postJson(route('examiner.assessments.assisted-checkin.process', ['id' => $timetableId]), [
                'matric_no' => $unregisteredMatric,
                'reason' => 'qr_damaged',
                'notes' => 'Attempting assisted check-in for unregistered candidate',
            ]);

        $processResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('reason', 'student_not_on_register');

        $this->assertDatabaseMissing('attendance_records', [
            'matric_no' => $unregisteredMatric,
            'timetable_id' => $timetableId,
        ]);
    }

    /**
     * Scenario 12: Assisted check-in creates a separate, auditable record in assisted_checkin_logs.
     */
    public function test_scenario_12_assisted_checkin_creates_audit_log_entry(): void
    {
        $matricNo = 'CSC/2021/001';
        $this->createOfficialStudent($matricNo, 'Audit Log Candidate', 'Computer Science', '400');
        $this->createRegisteredStudent($matricNo, $this->csDeptId, '400');

        $timetableId = $this->createTimetable('CSC401', $this->csDeptId, '400', 'confirmed');
        $this->enrollStudentOnTimetable($timetableId, $matricNo);

        $examiner = DB::table('examiners')->where('examiner_id', $this->examinerId)->first();

        $this->withSession($this->examinerSession($examiner))
            ->postJson(route('examiner.assessments.assisted-checkin.process', ['id' => $timetableId]), [
                'matric_no' => $matricNo,
                'reason' => 'camera_malfunction',
                'notes' => 'Device scanner lens cracked by accident.',
            ])->assertOk();

        // Verify assisted_checkin_logs row
        $this->assertDatabaseHas('assisted_checkin_logs', [
            'timetable_id' => $timetableId,
            'session_id' => $this->sessionId,
            'matric_no' => $matricNo,
            'examiner_id' => $this->examinerId,
            'reason' => 'camera_malfunction',
            'notes' => 'Device scanner lens cracked by accident.',
        ]);

        // Verify audit_log entry
        $this->assertDatabaseHas('audit_log', [
            'actor_id' => (string) $this->examinerId,
            'actor_type' => 'examiner',
            'action' => 'examiner.assisted_checkin',
        ]);
    }

    /**
     * Administrative Roster Management: Confirm, Reopen, and Cohort population.
     */
    public function test_admin_can_manage_assessment_roster_workflow(): void
    {
        $timetableId = $this->createTimetable('CSC401', $this->csDeptId, '400', 'incomplete');

        // Add 2 official students in CS 400
        $this->createOfficialStudent('CSC/2021/001', 'Student One', 'Computer Science', '400');
        $this->createOfficialStudent('CSC/2021/002', 'Student Two', 'Computer Science', '400');

        $adminExaminerId = DB::table('examiners')->insertGetId([
            'username' => 'admin_roster_test',
            'full_name' => 'System Administrator',
            'password_hash' => bcrypt('password123'),
            'role' => 'admin',
            'is_active' => true,
            'created_at' => now(),
        ]);

        $adminSession = [
            'examiner_id' => $adminExaminerId,
            'examiner_username' => 'admin_roster_test',
            'examiner_name' => 'System Administrator',
            'examiner_role' => 'admin',
        ];

        // 1. Populate cohort
        $this->withSession($adminSession)
            ->post(route('admin.timetable.roster.populate-cohort', ['entry' => $timetableId]))
            ->assertRedirect();

        $this->assertDatabaseCount('timetable_students', 2);
        $this->assertSame('incomplete', DB::table('timetables')->where('id', $timetableId)->value('register_status'));

        // 2. Confirm roster
        $this->withSession($adminSession)
            ->post(route('admin.timetable.roster.confirm', ['entry' => $timetableId]))
            ->assertRedirect();

        $this->assertSame('confirmed', DB::table('timetables')->where('id', $timetableId)->value('register_status'));
        $this->assertNotNull(DB::table('timetables')->where('id', $timetableId)->value('roster_confirmed_at'));

        // 3. Reopen roster
        $this->withSession($adminSession)
            ->post(route('admin.timetable.roster.unconfirm', ['entry' => $timetableId]))
            ->assertRedirect();

        $this->assertSame('incomplete', DB::table('timetables')->where('id', $timetableId)->value('register_status'));
    }

    /* -------------------------------------------------------------------------
     * HELPER METHODS
     * ------------------------------------------------------------------------- */

    private function createOfficialStudent(string $matricNo, string $fullName, string $department, string $level): void
    {
        DB::table('official_students')->updateOrInsert(
            ['matric_number' => $matricNo],
            [
                'full_name' => $fullName,
                'department' => $department,
                'faculty' => 'Faculty of Computing',
                'level' => $level,
                'programme' => 'BSc ' . $department,
                'academic_session' => '2025/2026',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function createRegisteredStudent(string $matricNo, int $deptId, string $level): void
    {
        DB::table('students')->updateOrInsert(
            ['matric_no' => $matricNo],
            [
                'full_name' => 'Registered ' . $matricNo,
                'department_id' => $deptId,
                'level' => $level,
                'session_id' => $this->sessionId,
                'photo_path' => 'demo-passports/student-020.jpg',
                'photo_status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('payment_records')->updateOrInsert(
            ['student_id' => $matricNo],
            [
                'session_id' => $this->sessionId,
                'rrr_number' => 'RRR-' . Str::slug($matricNo),
                'amount_declared' => 10000.00,
                'amount_confirmed' => 10000.00,
                'remita_response' => json_encode(['status' => 'verified']),
                'verified_at' => now(),
            ]
        );
    }

    private function createTimetable(
        string $courseCode,
        int $deptId,
        string $level,
        string $registerStatus = 'confirmed'
    ): int {
        return DB::table('timetables')->insertGetId([
            'exam_session_id' => $this->sessionId,
            'department_id' => $deptId,
            'level' => $level,
            'course_code' => $courseCode,
            'course_title' => 'Test Paper ' . $courseCode,
            'exam_date' => Carbon::today('Africa/Lagos')->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => 'CBT Lab A',
            'status' => 'scheduled',
            'examiner_id' => $this->examinerId,
            'register_status' => $registerStatus,
            'roster_confirmed_at' => $registerStatus === 'confirmed' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function enrollStudentOnTimetable(int $timetableId, string $matricNo, string $candidateType = 'regular'): void
    {
        DB::table('timetable_students')->insertOrIgnore([
            'timetable_id' => $timetableId,
            'matric_no' => $matricNo,
            'candidate_type' => $candidateType,
            'created_at' => now(),
        ]);
    }

    private function createCourseQrData(string $matricNo, int $timetableId): array
    {
        $tokenId = (string) Str::uuid();
        $payload = [
            'token_id' => $tokenId,
            'matric_no' => $matricNo,
            'student_id' => $matricNo,
            'session_id' => (int) $this->sessionId,
            'timetable_id' => $timetableId,
            'issued_at' => now()->timestamp,
        ];

        $encrypted = $this->cryptoService->encryptPayload($payload, $this->aesKey, $this->hmacSecret);

        DB::table('qr_tokens')->insert([
            'token_id' => $tokenId,
            'student_id' => $matricNo,
            'session_id' => $this->sessionId,
            'timetable_id' => $timetableId,
            'encrypted_payload' => $encrypted['encrypted_payload'],
            'hmac_signature' => $encrypted['hmac_signature'],
            'status' => 'UNUSED',
            'issued_at' => now(),
        ]);

        return [
            'token_id' => $tokenId,
            'encrypted_payload' => $encrypted['encrypted_payload'],
            'hmac_signature' => $encrypted['hmac_signature'],
            'session_id' => (int) $this->sessionId,
        ];
    }

    private function examinerSession(object $examiner): array
    {
        return [
            'examiner_id' => $examiner->examiner_id,
            'examiner_username' => $examiner->username,
            'examiner_name' => $examiner->full_name,
            'examiner_role' => $examiner->role,
        ];
    }
}
