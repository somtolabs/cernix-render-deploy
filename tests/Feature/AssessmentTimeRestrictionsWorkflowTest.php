<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CryptoService;
use App\Services\ExamPassService;
use App\Services\QrTokenService;
use App\Services\RegistrationService;
use App\Services\RemitaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AssessmentTimeRestrictionsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $examDate;
    private int $sessionId;
    private int $deptId;
    private int $examinerId;
    private int $timetableId;
    private object $examiner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->examDate = Carbon::today('Africa/Lagos')->toDateString();

        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        $this->sessionId = (int) $session->session_id;

        $dept = DB::table('departments')->first();
        $this->deptId = (int) $dept->dept_id;

        $this->examiner = DB::table('examiners')->where('username', 'examiner1')->first();
        $this->examinerId = (int) $this->examiner->examiner_id;

        // Ensure CSC401 is assigned to examiner1 and scheduled today 09:00 - 12:00
        $this->timetableId = (int) DB::table('timetables')
            ->where('exam_session_id', $this->sessionId)
            ->where('course_code', 'CSC401')
            ->value('id');

        DB::table('timetables')->where('id', $this->timetableId)->update([
            'examiner_id'     => $this->examinerId,
            'exam_date'       => $this->examDate,
            'start_time'      => '09:00',
            'end_time'        => '12:00',
            'venue'           => 'Faculty Lab 1',
            'status'          => 'scheduled',
            'assessment_type' => 'exam',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Test 1: Scanning is unavailable before the early window (e.g. 8:29 AM for 9:00 AM exam).
     */
    public function test_scanning_unavailable_before_early_window(): void
    {
        // 8:29:59 AM Africa/Lagos
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 08:29:59", 'Africa/Lagos'));

        // Attempt to start scan session
        $sessionResp = $this->withSession($this->examinerSession())
            ->post('/examiner/scan-session/start', [
                'timetable_id' => $this->timetableId,
            ]);

        $sessionResp->assertStatus(422);

        // Attempt verification
        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        $verifyResp = $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'REJECTED')
            ->assertJsonPath('reason', 'outside_time_window');

        $this->assertDatabaseMissing('attendance_records', [
            'token_id' => $token->token_id,
        ]);
    }

    /**
     * Test 2: Early scanning opens exactly 30 minutes before start (8:30:00 AM). Check-in permitted, logged as early_scan.
     */
    public function test_early_scanning_opens_exactly_30_minutes_before_start(): void
    {
        // Exactly 8:30:00 AM Africa/Lagos
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 08:30:00", 'Africa/Lagos'));

        // Start scan session succeeds
        $sessionResp = $this->withSession($this->examinerSession())
            ->postJson('/examiner/scan-session/start', [
                'timetable_id' => $this->timetableId,
            ]);

        $sessionResp->assertOk()->assertJsonPath('success', true);

        // Verification check-in succeeds
        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        $response = $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'APPROVED')
            ->assertJsonPath('checked_in', true);

        // Attendance record created
        $this->assertDatabaseHas('attendance_records', [
            'token_id'  => $token->token_id,
            'status'    => 'checked_in',
            'matric_no' => '220404008',
        ]);

        // Verification log has early_scan classification
        $this->assertDatabaseHas('verification_logs', [
            'token_id'            => $token->token_id,
            'decision'            => 'APPROVED',
            'scan_classification' => 'early_scan',
        ]);
    }

    /**
     * Test 3: Early scanning active midway through early window (8:45 AM).
     */
    public function test_early_scanning_active_midway_through_early_window(): void
    {
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 08:45:00", 'Africa/Lagos'));

        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        $response = $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'APPROVED');

        $this->assertDatabaseHas('verification_logs', [
            'token_id'            => $token->token_id,
            'scan_classification' => 'early_scan',
        ]);
    }

    /**
     * Test 4: Regular scanning begins exactly at scheduled start (9:00:00 AM). Check-in permitted, logged as regular_scan.
     */
    public function test_regular_scanning_begins_exactly_at_scheduled_start(): void
    {
        // Exactly 9:00:00 AM Africa/Lagos
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 09:00:00", 'Africa/Lagos'));

        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        $response = $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'APPROVED')
            ->assertJsonPath('checked_in', true);

        $this->assertDatabaseHas('verification_logs', [
            'token_id'            => $token->token_id,
            'decision'            => 'APPROVED',
            'scan_classification' => 'regular_scan',
        ]);
    }

    /**
     * Test 5: Regular scanning active mid-examination (10:30 AM). Check-in permitted, logged as regular_scan.
     */
    public function test_regular_scanning_active_during_examination(): void
    {
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 10:30:00", 'Africa/Lagos'));

        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        $response = $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'APPROVED');

        $this->assertDatabaseHas('verification_logs', [
            'token_id'            => $token->token_id,
            'scan_classification' => 'regular_scan',
        ]);
    }

    /**
     * Test 6: Late scanning begins exactly at scheduled end (12:00:00 PM). New entry is strictly rejected.
     */
    public function test_late_scanning_begins_exactly_at_scheduled_end_rejects_new_entry(): void
    {
        // Exactly 12:00:00 PM Africa/Lagos
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 12:00:00", 'Africa/Lagos'));

        // Student has never checked in
        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        $response = $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'REJECTED')
            ->assertJsonPath('reason', 'late_entry_not_permitted');

        // No attendance record created
        $this->assertDatabaseMissing('attendance_records', [
            'token_id' => $token->token_id,
        ]);

        // Verification log recorded with late_scan classification
        $this->assertDatabaseHas('verification_logs', [
            'token_id'            => $token->token_id,
            'decision'            => 'REJECTED',
            'scan_classification' => 'late_scan',
        ]);
    }

    /**
     * Test 7: Late scanning permits departure and physical submission for already checked-in student.
     */
    public function test_late_scanning_allows_departure_for_already_checked_in_student(): void
    {
        // 1. Student checks in during regular window (09:15 AM)
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 09:15:00", 'Africa/Lagos'));
        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        $checkInResp = $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'APPROVED');

        $this->assertDatabaseHas('attendance_records', [
            'token_id' => $token->token_id,
            'status'   => 'checked_in',
        ]);

        // 2. Late window opens (12:05 PM) - student scans to depart
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 12:05:00", 'Africa/Lagos'));

        $departureResp = $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'APPROVED')
            ->assertJsonPath('display_status', 'Departure Recorded');

        // Attendance updated to submitted
        $this->assertDatabaseHas('attendance_records', [
            'token_id'         => $token->token_id,
            'status'           => 'submitted',
            'exit_examiner_id' => $this->examinerId,
        ]);

        // Departure scan classified as late_scan
        $latestLog = DB::table('verification_logs')
            ->where('token_id', $token->token_id)
            ->orderByDesc('timestamp')
            ->first();

        $this->assertNotNull($latestLog);
        $this->assertSame('late_scan', $latestLog->scan_classification);
    }

    /**
     * Test 8: Late scanning remains active at the exact closing boundary (12:15:00 PM).
     */
    public function test_late_scanning_active_at_exact_closing_boundary(): void
    {
        // Student checked in earlier
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 09:30:00", 'Africa/Lagos'));
        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk();

        // Exactly 12:15:00 PM Africa/Lagos (end_time + 15m)
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 12:15:00", 'Africa/Lagos'));

        $response = $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'APPROVED');

        $this->assertDatabaseHas('attendance_records', [
            'token_id' => $token->token_id,
            'status'   => 'submitted',
        ]);
    }

    /**
     * Test 9: Scanning is closed immediately after late window closes (12:15:01 PM).
     */
    public function test_scanning_closed_immediately_after_late_window(): void
    {
        // 1 second after late window closing
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 12:15:01", 'Africa/Lagos'));

        // Session start rejected with 422
        $sessionResp = $this->withSession($this->examinerSession())
            ->post('/examiner/scan-session/start', [
                'timetable_id' => $this->timetableId,
            ]);

        $sessionResp->assertStatus(422);

        // Verification rejected
        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'REJECTED')
            ->assertJsonPath('reason', 'outside_time_window');
    }

    /**
     * Test 10: Scanning is closed long after scheduled end (1:00 PM).
     */
    public function test_scanning_closed_long_after_scheduled_end(): void
    {
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 13:00:00", 'Africa/Lagos'));

        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'REJECTED')
            ->assertJsonPath('reason', 'outside_time_window');
    }

    /**
     * Test 11: Completed assessment closes scanning immediately regardless of time.
     */
    public function test_completed_assessment_closes_scanning_immediately(): void
    {
        // Generate valid token while timetable is active/scheduled
        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        // Mid-exam time (10:00 AM)
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 10:00:00", 'Africa/Lagos'));

        DB::table('timetables')->where('id', $this->timetableId)->update([
            'status' => 'completed',
        ]);

        // Starting session rejected
        $this->withSession($this->examinerSession())
            ->postJson('/examiner/scan-session/start', ['timetable_id' => $this->timetableId])
            ->assertStatus(422);

        // Verification rejected
        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'REJECTED')
            ->assertJsonPath('reason', 'assessment_completed');

        // Submit attendance rejected
        $this->withSession($this->examinerSession())
            ->postJson('/examiner/submit-attendance', [
                'matric_no'    => '220404008',
                'timetable_id' => $this->timetableId,
                'session_id'   => $this->sessionId,
            ])
            ->assertStatus(422);
    }

    /**
     * Test 12: Cancelled assessment closes scanning immediately regardless of time.
     */
    public function test_cancelled_assessment_closes_scanning_immediately(): void
    {
        // Generate valid token while timetable is active/scheduled
        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        Carbon::setTestNow(Carbon::parse("{$this->examDate} 10:00:00", 'Africa/Lagos'));

        DB::table('timetables')->where('id', $this->timetableId)->update([
            'status' => 'cancelled',
        ]);

        $this->withSession($this->examinerSession())
            ->postJson('/examiner/scan-session/start', ['timetable_id' => $this->timetableId])
            ->assertStatus(422);

        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'REJECTED')
            ->assertJsonPath('reason', 'assessment_cancelled');
    }

    /**
     * Test 13: Missing end_time is treated as invalid schedule and blocks scanning.
     */
    public function test_missing_end_time_blocks_session_start_and_verification(): void
    {
        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        Carbon::setTestNow(Carbon::parse("{$this->examDate} 10:00:00", 'Africa/Lagos'));

        DB::table('timetables')->where('id', $this->timetableId)->update([
            'end_time' => null,
        ]);

        // Session start rejected with 422
        $this->withSession($this->examinerSession())
            ->postJson('/examiner/scan-session/start', ['timetable_id' => $this->timetableId])
            ->assertStatus(422);

        // Verification rejected with invalid_schedule
        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'REJECTED')
            ->assertJsonPath('reason', 'invalid_schedule');
    }

    /**
     * Test 14: end_time not after start_time is treated as invalid schedule.
     */
    public function test_end_time_not_after_start_time_blocks_session_and_verification(): void
    {
        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        Carbon::setTestNow(Carbon::parse("{$this->examDate} 10:00:00", 'Africa/Lagos'));

        DB::table('timetables')->where('id', $this->timetableId)->update([
            'start_time' => '10:00',
            'end_time'   => '09:00',
        ]);

        $this->withSession($this->examinerSession())
            ->postJson('/examiner/scan-session/start', ['timetable_id' => $this->timetableId])
            ->assertStatus(422);

        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'REJECTED')
            ->assertJsonPath('reason', 'invalid_schedule');
    }

    /**
     * Test 15: Tests (assessment_type = 'test') follow identical operational time rules.
     */
    public function test_test_assessment_type_follows_identical_rules(): void
    {
        DB::table('timetables')->where('id', $this->timetableId)->update([
            'assessment_type' => 'test',
        ]);

        // Before early window
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 08:20:00", 'Africa/Lagos'));
        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'REJECTED')
            ->assertJsonPath('reason', 'outside_time_window');

        // During regular window
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 09:30:00", 'Africa/Lagos'));

        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'APPROVED');
    }

    /**
     * Test 16: Make-up exams (assessment_type = 'makeup') follow identical operational time rules.
     */
    public function test_make_up_assessment_type_follows_identical_rules(): void
    {
        DB::table('timetables')->where('id', $this->timetableId)->update([
            'assessment_type' => 'makeup',
        ]);

        // During late window, new entry rejected
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 12:05:00", 'Africa/Lagos'));
        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'REJECTED')
            ->assertJsonPath('reason', 'late_entry_not_permitted');
    }

    /**
     * Test 17: Direct POST to submit attendance rejected when outside scanning windows.
     */
    public function test_submit_attendance_rejected_when_outside_window(): void
    {
        // 2 hours after exam end
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 14:00:00", 'Africa/Lagos'));

        $this->withSession($this->examinerSession())
            ->postJson('/examiner/submit-attendance', [
                'matric_no'    => '220404008',
                'timetable_id' => $this->timetableId,
                'session_id'   => $this->sessionId,
            ])
            ->assertStatus(422);
    }

    /**
     * Test 18: Late departure is atomic, marks submitted, and repeated scans are handled safely.
     */
    public function test_late_departure_atomic_and_idempotent(): void
    {
        // Student checks in during regular window (09:10 AM)
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 09:10:00", 'Africa/Lagos'));
        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'APPROVED');

        // Student scans to depart during late window (12:05 PM)
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 12:05:00", 'Africa/Lagos'));

        $firstDeparture = $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'APPROVED');

        $this->assertDatabaseHas('attendance_records', [
            'token_id' => $token->token_id,
            'status'   => 'submitted',
        ]);

        // Student scans again during late window - handled safely without record duplication
        $secondDeparture = $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk();

        // Exactly one attendance record exists for this token
        $this->assertSame(1, DB::table('attendance_records')->where('token_id', $token->token_id)->count());
    }

    /**
     * Test 19: Scan classifications early_scan, regular_scan, and late_scan are persisted to database.
     */
    public function test_scan_classifications_persisted_in_database(): void
    {
        // 1. Early scan at 08:35 AM
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 08:35:00", 'Africa/Lagos'));
        $token1 = $this->registerToken();
        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $this->qrData($token1)])
            ->assertOk();

        $this->assertDatabaseHas('verification_logs', [
            'token_id'            => $token1->token_id,
            'scan_classification' => 'early_scan',
        ]);

        // 2. Regular scan at 10:00 AM
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 10:00:00", 'Africa/Lagos'));
        $token2 = $this->registerToken('220404009');
        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $this->qrData($token2)])
            ->assertOk();

        $this->assertDatabaseHas('verification_logs', [
            'token_id'            => $token2->token_id,
            'scan_classification' => 'regular_scan',
        ]);

        // 3. Late scan departure at 12:08 PM for token2
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 12:08:00", 'Africa/Lagos'));
        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $this->qrData($token2)])
            ->assertOk();

        $this->assertDatabaseHas('verification_logs', [
            'token_id'            => $token2->token_id,
            'scan_classification' => 'late_scan',
        ]);
    }

    /**
     * Test 20: API examiner verify endpoint enforces time window restrictions and classifications.
     */
    public function test_api_examiner_verify_enforces_time_restrictions_and_classifications(): void
    {
        // Set test time before generating JWT token so token iat matches
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 08:20:00", 'Africa/Lagos'));

        $user = User::factory()->create([
            'email' => 'examiner1',
            'role'  => 'examiner',
        ]);
        $jwtToken = JWTAuth::fromUser($user);

        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        // Outside time window (08:20 AM)
        $outResp = $this->withToken($jwtToken)->postJson('/api/examiner/verify', [
            'qr_data' => $qrData,
        ]);
        $outResp->assertOk()
            ->assertJsonPath('data.status', 'REJECTED')
            ->assertJsonPath('data.reason', 'outside_time_window');

        // Inside regular window (10:00 AM)
        Carbon::setTestNow(Carbon::parse("{$this->examDate} 10:00:00", 'Africa/Lagos'));
        $inResp = $this->withToken($jwtToken)->postJson('/api/examiner/verify', [
            'qr_data' => $qrData,
        ]);
        $inResp->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');

        $this->assertDatabaseHas('verification_logs', [
            'token_id'            => $token->token_id,
            'scan_classification' => 'regular_scan',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function registerToken(string $matric = '220404008'): object
    {
        $session = DB::table('exam_sessions')->where('session_id', $this->sessionId)->first();

        DB::table('official_students')->updateOrInsert(
            ['matric_number' => $matric],
            [
                'full_name'   => "Demo Student {$matric}",
                'department'  => 'Computer Science',
                'faculty'     => 'Faculty of Computing',
                'level'       => '400',
                'status'      => 'active',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        (new RegistrationService())->registerStudent([
            'matric_no'  => $matric,
            'session_id' => (int) $session->session_id,
            'photo_path' => 'photos/student-submissions/test.jpg',
        ]);

        DB::table('students')->where('matric_no', $matric)->update([
            'photo_status'      => 'approved',
            'photo_reviewed_by' => 'test-admin',
            'photo_reviewed_at' => now(),
            'updated_at'        => now(),
        ]);

        if (DB::getSchemaBuilder()->hasTable('timetable_students')) {
            DB::table('timetable_students')->insertOrIgnore([
                'timetable_id'   => $this->timetableId,
                'matric_no'      => $matric,
                'candidate_type' => 'regular',
                'created_at'     => now(),
            ]);
        }

        $result = (new ExamPassService(
            $this->createMock(RemitaService::class),
            new QrTokenService(new CryptoService),
        ))->generate(
            $matric,
            (int) $session->session_id,
            $this->timetableId,
            'TEST-DEMO',
            100000,
        );

        return DB::table('qr_tokens')->where('token_id', $result['token_id'])->first();
    }

    private function qrData(object $token): array
    {
        return [
            'token_id'          => $token->token_id,
            'encrypted_payload' => $token->encrypted_payload,
            'hmac_signature'    => $token->hmac_signature,
            'session_id'        => $token->session_id,
        ];
    }

    private function examinerSession(): array
    {
        return [
            'examiner_id'                  => $this->examinerId,
            'examiner_username'            => $this->examiner->username,
            'examiner_name'                => $this->examiner->full_name,
            'examiner_role'                => $this->examiner->role,
            'examiner_active_timetable_id' => $this->timetableId,
            'examiner_active_timetable'    => [
                'id'              => $this->timetableId,
                'course_code'     => 'CSC401',
                'course_title'    => 'Artificial Intelligence',
                'venue'           => 'Faculty Lab 1',
                'dept_name'       => 'Computer Science',
                'level'           => '400',
                'start_time'      => '09:00',
                'end_time'        => '12:00',
                'assessment_type' => 'exam',
            ],
        ];
    }
}
