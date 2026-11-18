<?php

namespace Tests\Feature;

use App\Services\CryptoService;
use App\Services\ExamPassService;
use App\Services\QrTokenService;
use App\Services\RegistrationService;
use App\Services\RemitaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExaminerAttendanceCheckInWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $today = Carbon::today('Africa/Lagos')->toDateString();
        DB::table('timetables')->update([
            'exam_date'  => $today,
            'start_time' => '09:00',
            'end_time'   => '12:00',
        ]);
        Carbon::setTestNow(Carbon::parse("{$today} 10:00:00", 'Africa/Lagos'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Test 1: A valid student QR scan creates a persistent attendance record.
     */
    public function test_valid_student_qr_scan_creates_persistent_attendance_record(): void
    {
        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        $response = $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'APPROVED')
            ->assertJsonPath('display_status', 'Verified')
            ->assertJsonPath('checked_in', true);

        // Attendance record must exist in the database
        $this->assertDatabaseHas('attendance_records', [
            'token_id' => $token->token_id,
            'matric_no' => '220404008',
            'status' => 'checked_in',
        ]);

        // Token must be marked USED
        $this->assertDatabaseHas('qr_tokens', [
            'token_id' => $token->token_id,
            'status' => 'USED',
        ]);

        // Verification log must record APPROVED
        $this->assertDatabaseHas('verification_logs', [
            'token_id' => $token->token_id,
            'decision' => 'APPROVED',
        ]);
    }

    /**
     * Test 2: The attendance record contains the correct student and timetable references.
     */
    public function test_attendance_record_references_correct_student_and_timetable_data(): void
    {
        $token = $this->registerToken();
        $session = $this->examinerSession();
        $activeTimetableId = $session['examiner_active_timetable_id'];

        $this->withSession($session)
            ->postJson('/examiner/verify', ['qr_data' => $this->qrData($token)])
            ->assertOk()
            ->assertJsonPath('status', 'APPROVED');

        $record = DB::table('attendance_records')
            ->where('token_id', $token->token_id)
            ->first();

        $this->assertNotNull($record, 'Attendance record should be created');
        $this->assertSame('220404008', $record->matric_no);
        $this->assertSame((int) $activeTimetableId, (int) $record->timetable_id);
        $this->assertSame((int) $token->session_id, (int) $record->session_id);
        $this->assertSame((int) $session['examiner_id'], (int) $record->entry_examiner_id);
    }

    /**
     * Test 3: The status is checked_in and the check-in timestamp is populated.
     */
    public function test_attendance_record_status_is_checked_in_and_timestamp_populated(): void
    {
        $token = $this->registerToken();

        $beforeScan = Carbon::now()->subSecond();

        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $this->qrData($token)])
            ->assertOk();

        $record = DB::table('attendance_records')
            ->where('token_id', $token->token_id)
            ->first();

        $this->assertNotNull($record);
        $this->assertSame('checked_in', $record->status);
        $this->assertNotNull($record->checked_in_at);

        $checkedInAt = Carbon::parse($record->checked_in_at);
        $this->assertTrue($checkedInAt->gte($beforeScan));
        $this->assertTrue($checkedInAt->lte(Carbon::now()->addSecond()));
    }

    /**
     * Test 4: An invalid or tampered QR code does not create attendance.
     */
    public function test_invalid_qr_code_does_not_create_attendance(): void
    {
        $token = $this->registerToken();
        $qrData = $this->qrData($token);
        // Tamper with HMAC signature
        $qrData['hmac_signature'] = str_repeat('0', 64);

        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'REJECTED')
            ->assertJsonPath('reason', 'token_record_mismatch');

        // No attendance record created
        $this->assertDatabaseMissing('attendance_records', [
            'token_id' => $token->token_id,
        ]);

        // Token must remain UNUSED
        $this->assertDatabaseHas('qr_tokens', [
            'token_id' => $token->token_id,
            'status' => 'UNUSED',
        ]);
    }

    /**
     * Test 5: An ineligible student does not get checked in.
     */
    public function test_ineligible_student_does_not_get_checked_in(): void
    {
        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        // Register student in a different department (Mathematics)
        $mathDeptId = DB::table('departments')->where('dept_name', '!=', 'Computer Science')->value('dept_id')
            ?? DB::table('departments')->insertGetId([
                'dept_name' => 'Mathematics',
                'faculty' => 'Faculty of Science',
                'created_at' => now(),
                'updated_at' => now(),
            ], 'dept_id');

        DB::table('official_students')->updateOrInsert(
            ['matric_number' => '220404099'],
            [
                'full_name' => 'Ineligible Student',
                'department' => 'Mathematics',
                'faculty' => 'Faculty of Science',
                'level' => '400',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('students')->updateOrInsert(
            ['matric_no' => '220404099'],
            [
                'full_name' => 'Ineligible Student',
                'department_id' => $mathDeptId,
                'level' => '400',
                'session_id' => (int) $session->session_id,
                'photo_status' => 'approved',
                'photo_path' => 'demo-passports/student-020.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Timetable is CSC401 in Computer Science department
        $timetableId = DB::table('timetables')
            ->where('exam_session_id', $session->session_id)
            ->where('course_code', 'CSC401')
            ->value('id');

        // Verified payment
        DB::table('payment_records')->updateOrInsert(
            ['student_id' => '220404099'],
            [
                'session_id' => (int) $session->session_id,
                'rrr_number' => 'RRR-INELIGIBLE-99',
                'amount_declared' => 100000,
                'amount_confirmed' => 100000,
                'remita_response' => json_encode(['status' => '00']),
                'verified_at' => now(),
            ]
        );

        $token = $this->createEncryptedToken('220404099', (int) $session->session_id, (int) $timetableId);

        $response = $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $this->qrData($token)])
            ->assertOk()
            ->assertJsonPath('status', 'REJECTED');

        $this->assertContains($response->json('reason'), ['register_incomplete', 'student_not_on_register', 'course_not_assigned']);

        // No attendance record created
        $this->assertDatabaseMissing('attendance_records', [
            'matric_no' => '220404099',
        ]);

        // Token remains UNUSED
        $this->assertDatabaseHas('qr_tokens', [
            'token_id' => $token->token_id,
            'status' => 'UNUSED',
        ]);
    }

    /**
     * Test 6: A student with a QR pass for a different examination session/hall does not get checked in.
     */
    public function test_wrong_examination_session_or_hall_does_not_create_attendance(): void
    {
        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        $csc401Id = (int) DB::table('timetables')
            ->where('exam_session_id', $session->session_id)
            ->where('course_code', 'CSC401')
            ->value('id');

        // Create second timetable (CSC405) in different hall
        $secondTimetableId = (int) (DB::table('timetables')->where('course_code', 'CSC405')->value('id') ?? DB::table('timetables')->insertGetId([
            'exam_session_id' => $session->session_id,
            'department_id' => DB::table('timetables')->where('id', $csc401Id)->value('department_id'),
            'course_code' => 'CSC405',
            'course_title' => 'Database Systems',
            'level' => '400',
            'venue' => 'Science Hall B',
            'exam_date' => today()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'status' => 'scheduled',
            'assessment_type' => 'exam',
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        // Ensure student 220404008 is registered in students table
        $this->registerToken();

        // Generate token for CSC405
        $token = $this->createEncryptedToken('220404008', (int) $session->session_id, $secondTimetableId);

        // Examiner active session is CSC401 (not CSC405)
        $examinerSession = $this->examinerSession();

        $response = $this->withSession($examinerSession)
            ->postJson('/examiner/verify', ['qr_data' => $this->qrData($token)])
            ->assertOk()
            ->assertJsonPath('status', 'REJECTED')
            ->assertJsonPath('reason', 'wrong_session')
            ->assertJsonPath('display_status', 'Wrong Exam Session');

        // No attendance record created
        $this->assertDatabaseMissing('attendance_records', [
            'token_id' => $token->token_id,
        ]);

        // Token remains UNUSED so student can scan in their correct hall
        $this->assertDatabaseHas('qr_tokens', [
            'token_id' => $token->token_id,
            'status' => 'UNUSED',
        ]);

        // Verification log must NOT have an APPROVED record
        $this->assertDatabaseMissing('verification_logs', [
            'token_id' => $token->token_id,
            'decision' => 'APPROVED',
        ]);
    }

    /**
     * Test 7: An invalid or inactive examination session does not create attendance.
     */
    public function test_invalid_examination_session_does_not_create_attendance(): void
    {
        $token = $this->registerToken();
        $qrData = $this->qrData($token);
        // Set invalid session ID
        $qrData['session_id'] = 999999;

        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'REJECTED');

        $this->assertDatabaseMissing('attendance_records', [
            'token_id' => $token->token_id,
        ]);

        $this->assertDatabaseHas('qr_tokens', [
            'token_id' => $token->token_id,
            'status' => 'UNUSED',
        ]);
    }

    /**
     * Test 8: An attendance insertion failure rolls back and does not return a misleading successful check-in.
     */
    public function test_attendance_insertion_failure_rolls_back_and_does_not_report_success(): void
    {
        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        // Install a trigger that simulates a database failure during attendance insertion
        DB::unprepared(
            "CREATE TRIGGER fail_attendance_record_insert
             BEFORE INSERT ON attendance_records
             BEGIN
                 SELECT RAISE(ABORT, 'simulated attendance insertion failure');
             END"
        );

        $response = $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'ERROR')
            ->assertJsonPath('display_status', 'Error Verifying QR');

        // Crucial requirement: Token must NOT be consumed
        $this->assertDatabaseHas('qr_tokens', [
            'token_id' => $token->token_id,
            'status' => 'UNUSED',
        ]);

        // No attendance record persisted
        $this->assertDatabaseMissing('attendance_records', [
            'token_id' => $token->token_id,
        ]);

        // No APPROVED verification log
        $this->assertDatabaseMissing('verification_logs', [
            'token_id' => $token->token_id,
            'decision' => 'APPROVED',
        ]);
    }

    /**
     * Test 9: Repeated scans do not create duplicate attendance records.
     */
    public function test_repeated_scans_do_not_create_duplicate_attendance_records(): void
    {
        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        // First scan: successful check-in
        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'APPROVED')
            ->assertJsonPath('display_status', 'Verified');

        $initialAttendance = DB::table('attendance_records')
            ->where('token_id', $token->token_id)
            ->first();

        $this->assertNotNull($initialAttendance);

        // Second scan: duplicate scan attempt
        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'DUPLICATE')
            ->assertJsonPath('display_status', 'Already Used');

        // Third scan: another duplicate scan attempt
        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'DUPLICATE');

        // Exactly 1 attendance record must exist
        $totalAttendanceCount = DB::table('attendance_records')
            ->where('token_id', $token->token_id)
            ->count();

        $this->assertSame(1, $totalAttendanceCount);

        // Original check-in timestamp and status preserved
        $currentAttendance = DB::table('attendance_records')
            ->where('token_id', $token->token_id)
            ->first();

        $this->assertSame($initialAttendance->id, $currentAttendance->id);
        $this->assertSame($initialAttendance->checked_in_at, $currentAttendance->checked_in_at);
        $this->assertSame('checked_in', $currentAttendance->status);
    }

    /**
     * Test 10: Unauthorized examiner is rejected without consuming pass or creating attendance.
     */
    public function test_unauthorized_examiner_does_not_consume_pass_or_create_attendance(): void
    {
        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        // Assign CSC401 to examiner 2
        $examiner2 = DB::table('examiners')->where('examiner_id', '!=', 1)->first()
            ?? DB::table('examiners')->insertGetId([
                'username' => 'examiner2',
                'full_name' => 'Prof. Second Examiner',
                'role' => 'examiner',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'examiner_id');

        $examiner2Id = is_object($examiner2) ? (int) $examiner2->examiner_id : (int) $examiner2;

        DB::table('timetables')
            ->where('exam_session_id', $session->session_id)
            ->where('course_code', 'CSC401')
            ->update(['examiner_id' => $examiner2Id]);

        $token = $this->registerToken();

        // Examiner 1 tries to scan (not assigned to this exam)
        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $this->qrData($token)])
            ->assertOk()
            ->assertJsonPath('status', 'REJECTED')
            ->assertJsonPath('reason', 'wrong_examiner')
            ->assertJsonPath('display_status', 'Not Your Assignment');

        $this->assertDatabaseMissing('attendance_records', [
            'token_id' => $token->token_id,
        ]);

        $this->assertDatabaseHas('qr_tokens', [
            'token_id' => $token->token_id,
            'status' => 'UNUSED',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helper Methods
    // -------------------------------------------------------------------------

    private function createEncryptedToken(string $matricNo, int $sessionId, int $timetableId): object
    {
        $session = DB::table('exam_sessions')->where('session_id', $sessionId)->first();
        $student = DB::table('students')->where('matric_no', $matricNo)->first();
        $tokenId = (string) \Illuminate\Support\Str::uuid();

        $crypto = new CryptoService();
        $payload = [
            'token_id' => $tokenId,
            'matric_no' => $matricNo,
            'full_name' => $student->full_name ?? 'Test Student',
            'session_id' => $sessionId,
            'timetable_id' => $timetableId,
            'issued_at' => now()->toIso8601String(),
        ];

        $encrypted = $crypto->encryptPayload($payload, $session->aes_key, $session->hmac_secret);

        DB::table('qr_tokens')->insert([
            'token_id' => $tokenId,
            'student_id' => $matricNo,
            'session_id' => $sessionId,
            'timetable_id' => $timetableId,
            'encrypted_payload' => $encrypted['encrypted_payload'],
            'hmac_signature' => $encrypted['hmac_signature'],
            'status' => 'UNUSED',
            'issued_at' => now(),
        ]);

        return DB::table('qr_tokens')->where('token_id', $tokenId)->first();
    }

    private function registerToken(): object
    {
        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        DB::table('official_students')->updateOrInsert(
            ['matric_number' => '220404008'],
            [
                'full_name' => 'Demo Student',
                'department' => 'Computer Science',
                'faculty' => 'Faculty of Computing',
                'level' => '400',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        (new RegistrationService())->registerStudent([
            'matric_no' => '220404008',
            'session_id' => (int) $session->session_id,
            'photo_path' => 'photos/student-submissions/test.jpg',
        ]);
        DB::table('students')->where('matric_no', '220404008')->update([
            'photo_status' => 'approved',
            'photo_reviewed_by' => 'test-admin',
            'photo_reviewed_at' => now(),
            'updated_at' => now(),
        ]);
        $timetableId = DB::table('timetables')
            ->where('exam_session_id', $session->session_id)
            ->where('course_code', 'CSC401')
            ->value('id');
        DB::table('timetables')->where('id', $timetableId)->update([
            'register_status' => 'confirmed',
            'roster_confirmed_at' => now(),
        ]);
        if (\Illuminate\Support\Facades\Schema::hasTable('timetable_students')) {
            DB::table('timetable_students')->insertOrIgnore([
                'timetable_id' => $timetableId,
                'matric_no' => '220404008',
                'candidate_type' => 'regular',
                'created_at' => now(),
            ]);
        }
        $result = (new ExamPassService(
            $this->createMock(RemitaService::class),
            new QrTokenService(new CryptoService),
        ))->generate(
            '220404008',
            (int) $session->session_id,
            (int) $timetableId,
            'TEST-DEMO',
            100000,
        );

        return DB::table('qr_tokens')->where('token_id', $result['token_id'])->first();
    }

    private function qrData(object $token): array
    {
        return [
            'token_id' => $token->token_id,
            'encrypted_payload' => $token->encrypted_payload,
            'hmac_signature' => $token->hmac_signature,
            'session_id' => $token->session_id,
        ];
    }

    private function examinerSession(): array
    {
        $examiner    = DB::table('examiners')->where('username', 'examiner1')->first();
        $examSession = DB::table('exam_sessions')->where('is_active', true)->first();
        $timetableId = $examSession
            ? (int) DB::table('timetables')
                ->where('exam_session_id', $examSession->session_id)
                ->where('course_code', 'CSC401')
                ->value('id')
            : 0;

        $session = [
            'examiner_id'       => (int) $examiner->examiner_id,
            'examiner_username' => $examiner->username,
            'examiner_name'     => $examiner->full_name,
            'examiner_role'     => $examiner->role,
        ];

        if ($timetableId) {
            $session['examiner_active_timetable_id'] = $timetableId;
            $session['examiner_active_timetable']    = [
                'id'              => $timetableId,
                'course_code'     => 'CSC401',
                'course_title'    => 'Artificial Intelligence',
                'venue'           => 'Faculty Lab 1',
                'dept_name'       => 'Computer Science',
                'level'           => '400',
                'start_time'      => '',
                'end_time'        => '',
                'assessment_type' => 'exam',
            ];
        }

        return $session;
    }
}
