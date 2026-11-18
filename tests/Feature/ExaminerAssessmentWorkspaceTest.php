<?php

namespace Tests\Feature;

use App\Services\CryptoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExaminerAssessmentWorkspaceTest extends TestCase
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
     * Test 1: Assessment listing loads for authenticated examiner and shows assigned assessments.
     */
    public function test_assessment_listing_loads_for_authenticated_examiner(): void
    {
        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();
        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        // Assign CSC401 to examiner1
        $timetableId = DB::table('timetables')
            ->where('exam_session_id', $session->session_id)
            ->where('course_code', 'CSC401')
            ->value('id');

        DB::table('timetables')->where('id', $timetableId)->update([
            'examiner_id' => $examiner->examiner_id,
        ]);

        $response = $this->withSession($this->examinerSession($examiner, $timetableId))
            ->get('/examiner/assessments');

        $response->assertOk()
            ->assertSee('CSC401')
            ->assertSee('Artificial Intelligence')
            ->assertSee('Open Workspace');
    }

    /**
     * Test 2: Assessment workspace loads correctly for assigned examiner.
     */
    public function test_assessment_workspace_loads_correctly_for_assigned_examiner(): void
    {
        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();
        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        $timetableId = DB::table('timetables')
            ->where('exam_session_id', $session->session_id)
            ->where('course_code', 'CSC401')
            ->value('id');

        DB::table('timetables')->where('id', $timetableId)->update([
            'examiner_id' => $examiner->examiner_id,
        ]);

        $response = $this->withSession($this->examinerSession($examiner, $timetableId))
            ->get("/examiner/assessments/{$timetableId}");

        $response->assertOk()
            ->assertSee('CSC401 Workspace')
            ->assertSee('Faculty Lab 1')
            ->assertSee('Assigned Student Roster')
            ->assertSee('Verification Scans');
    }

    /**
     * Test 3: Unassigned examiner receives HTTP 403 on assessment workspace.
     */
    public function test_unassigned_examiner_receives_http_403_on_assessment_workspace(): void
    {
        $examiner1 = DB::table('examiners')->where('username', 'examiner1')->first();
        $examiner2 = $this->createExaminer('examiner2');
        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        $timetableId = DB::table('timetables')
            ->where('exam_session_id', $session->session_id)
            ->where('course_code', 'CSC401')
            ->value('id');

        // Assign to examiner1
        DB::table('timetables')->where('id', $timetableId)->update([
            'examiner_id' => $examiner1->examiner_id,
        ]);

        // Examiner2 attempts to access examiner1's assessment workspace
        $response = $this->withSession($this->examinerSession($examiner2))
            ->get("/examiner/assessments/{$timetableId}");

        $response->assertStatus(403);
    }

    /**
     * Test 4: Correct assessment information and duration displayed.
     */
    public function test_correct_assessment_information_and_duration_displayed(): void
    {
        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();
        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        $timetableId = DB::table('timetables')
            ->where('exam_session_id', $session->session_id)
            ->where('course_code', 'CSC401')
            ->value('id');

        DB::table('timetables')->where('id', $timetableId)->update([
            'examiner_id' => $examiner->examiner_id,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => 'Main Auditorium',
        ]);

        $response = $this->withSession($this->examinerSession($examiner, $timetableId))
            ->get("/examiner/assessments/{$timetableId}");

        $response->assertOk()
            ->assertSee('CSC401')
            ->assertSee('Main Auditorium')
            ->assertSee('09:00 - 12:00')
            ->assertSee('3 hours (180 mins)');
    }

    /**
     * Test 5: Scans are grouped under the correct assessment.
     */
    public function test_scans_are_grouped_under_the_correct_assessment(): void
    {
        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();
        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        $csc401Id = DB::table('timetables')
            ->where('exam_session_id', $session->session_id)
            ->where('course_code', 'CSC401')
            ->value('id');

        $cys401Id = DB::table('timetables')
            ->where('exam_session_id', $session->session_id)
            ->where('course_code', 'CYS401')
            ->value('id');

        DB::table('timetables')->where('id', $csc401Id)->update(['examiner_id' => $examiner->examiner_id]);
        DB::table('timetables')->where('id', $cys401Id)->update(['examiner_id' => $examiner->examiner_id]);

        // Insert student and token for CSC401
        $this->createStudent('CSC-STUDENT-001', (int) $session->session_id);
        $token1 = $this->createToken('CSC-STUDENT-001', (int) $session->session_id, (int) $csc401Id);

        // Insert scan for CSC401
        DB::table('verification_logs')->insert([
            'token_id' => $token1->token_id,
            'examiner_id' => $examiner->examiner_id,
            'decision' => 'APPROVED',
            'timestamp' => now(),
            'device_fp' => 'device-1234',
            'ip_address' => '127.0.0.1',
            'timetable_id' => $csc401Id,
        ]);

        // Workspace for CSC401 must show the scan
        $resp1 = $this->withSession($this->examinerSession($examiner, $csc401Id))
            ->get("/examiner/assessments/{$csc401Id}");
        $resp1->assertOk()
            ->assertSee('CSC-STUDENT-001');

        // Workspace for CYS401 must NOT show the scan
        $resp2 = $this->withSession($this->examinerSession($examiner, $cys401Id))
            ->get("/examiner/assessments/{$cys401Id}");
        $resp2->assertOk()
            ->assertDontSee('CSC-STUDENT-001');
    }

    /**
     * Test 6: Wrong-hall scan is attributed to active session's timetable_id.
     */
    public function test_wrong_hall_scan_is_attributed_to_active_sessions_timetable_id(): void
    {
        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();
        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        $mat101Id = DB::table('timetables')
            ->where('exam_session_id', $session->session_id)
            ->where('course_code', 'CSC101')
            ->value('id');

        $csc401Id = DB::table('timetables')
            ->where('exam_session_id', $session->session_id)
            ->where('course_code', 'CSC401')
            ->value('id');

        DB::table('timetables')->where('id', $mat101Id)->update(['examiner_id' => $examiner->examiner_id]);

        // Student pass is for CSC401
        $this->createStudent('WRONG-HALL-001', (int) $session->session_id);
        $token = $this->createToken('WRONG-HALL-001', (int) $session->session_id, (int) $csc401Id);

        // Examiner is invigilating CSC101 and scans student's CSC401 pass
        $qrData = [
            'token_id' => $token->token_id,
            'encrypted_payload' => $token->encrypted_payload,
            'hmac_signature' => $token->hmac_signature,
            'session_id' => $token->session_id,
        ];

        $sessionData = $this->examinerSession($examiner, $mat101Id);
        $response = $this->withSession($sessionData)
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'REJECTED')
            ->assertJsonPath('reason', 'wrong_session');

        // Verification log must attribute the rejected scan to active timetable CSC101
        $log = DB::table('verification_logs')->where('token_id', $token->token_id)->first();
        $this->assertNotNull($log);
        $this->assertSame((int) $mat101Id, (int) $log->timetable_id);

        // In CSC101's workspace, the scan must be listed
        $wsResp = $this->withSession($sessionData)
            ->get("/examiner/assessments/{$mat101Id}");
        $wsResp->assertOk()
            ->assertSee('WRONG-HALL-001')
            ->assertSee('wrong session');
    }

    /**
     * Test 7: Invalid or tampered QR attempts do not fabricate assessment associations.
     */
    public function test_invalid_or_tampered_qr_attempts_do_not_fabricate_assessment_associations(): void
    {
        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();
        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        $timetableId = DB::table('timetables')
            ->where('exam_session_id', $session->session_id)
            ->where('course_code', 'CSC401')
            ->value('id');

        DB::table('timetables')->where('id', $timetableId)->update(['examiner_id' => $examiner->examiner_id]);

        $invalidQr = [
            'token_id' => (string) Str::uuid(),
            'encrypted_payload' => 'fake_payload',
            'hmac_signature' => str_repeat('a', 64),
            'session_id' => $session->session_id,
        ];

        $this->withSession($this->examinerSession($examiner, $timetableId))
            ->postJson('/examiner/verify', ['qr_data' => $invalidQr])
            ->assertOk()
            ->assertJsonPath('status', 'REJECTED')
            ->assertJsonPath('reason', 'token_not_found');

        // No verification_logs row should have been created with a fabricated assessment association
        $this->assertDatabaseMissing('verification_logs', [
            'token_id' => $invalidQr['token_id'],
        ]);
    }

    /**
     * Test 8: Student examination history groups records strictly by assessment without cross-course leakage.
     */
    public function test_student_examination_history_groups_records_strictly_by_assessment(): void
    {
        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();
        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        $csc401Id = DB::table('timetables')
            ->where('exam_session_id', $session->session_id)
            ->where('course_code', 'CSC401')
            ->value('id');

        $cys401Id = DB::table('timetables')
            ->where('exam_session_id', $session->session_id)
            ->where('course_code', 'CYS401')
            ->value('id');

        DB::table('timetables')->where('id', $csc401Id)->update(['examiner_id' => $examiner->examiner_id]);
        DB::table('timetables')->where('id', $cys401Id)->update(['examiner_id' => $examiner->examiner_id]);

        $matric = 'MULTI-COURSE-001';
        $this->createStudent($matric, (int) $session->session_id);

        $tokenCSC = $this->createToken($matric, (int) $session->session_id, (int) $csc401Id);
        $tokenCYS = $this->createToken($matric, (int) $session->session_id, (int) $cys401Id);

        // CSC scan
        DB::table('verification_logs')->insert([
            'token_id' => $tokenCSC->token_id,
            'examiner_id' => $examiner->examiner_id,
            'decision' => 'APPROVED',
            'timestamp' => Carbon::now()->subMinutes(30),
            'device_fp' => 'dev-csc',
            'ip_address' => '10.0.0.1',
            'timetable_id' => $csc401Id,
        ]);

        // CYS scan
        DB::table('verification_logs')->insert([
            'token_id' => $tokenCYS->token_id,
            'examiner_id' => $examiner->examiner_id,
            'decision' => 'APPROVED',
            'timestamp' => Carbon::now()->subMinutes(10),
            'device_fp' => 'dev-cys',
            'ip_address' => '10.0.0.2',
            'timetable_id' => $cys401Id,
        ]);

        $response = $this->withSession($this->examinerSession($examiner, $csc401Id))
            ->get("/examiner/students/{$matric}");

        $response->assertOk()
            ->assertSee($matric)
            ->assertSee('CSC401')
            ->assertSee('CYS401')
            ->assertSee('dev-csc')
            ->assertSee('dev-cys');
    }

    /**
     * Test 9: Attendance and physical submission statuses are accurate and manageable.
     */
    public function test_attendance_and_physical_submission_statuses_are_accurate(): void
    {
        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();
        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        $timetableId = DB::table('timetables')
            ->where('exam_session_id', $session->session_id)
            ->where('course_code', 'CSC401')
            ->value('id');

        DB::table('timetables')->where('id', $timetableId)->update([
            'examiner_id' => $examiner->examiner_id,
            'exam_date'   => Carbon::today('Africa/Lagos')->toDateString(),
            'start_time'  => '09:00',
            'end_time'    => '12:00',
            'status'      => 'scheduled',
        ]);

        Carbon::setTestNow(Carbon::parse(Carbon::today('Africa/Lagos')->toDateString() . ' 10:00:00', 'Africa/Lagos'));

        $matric = 'SUBMIT-STUDENT-001';
        $this->createStudent($matric, (int) $session->session_id);

        // Create checked_in attendance record
        DB::table('attendance_records')->insert([
            'matric_no' => $matric,
            'timetable_id' => $timetableId,
            'session_id' => $session->session_id,
            'token_id' => (string) Str::uuid(),
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'entry_examiner_id' => $examiner->examiner_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Workspace must reflect "Still Writing"
        $response = $this->withSession($this->examinerSession($examiner, $timetableId))
            ->get("/examiner/assessments/{$timetableId}");

        $response->assertOk()
            ->assertSee('Still Writing')
            ->assertSee('Mark Submitted');

        // Mark submitted via POST /examiner/submit-attendance
        $submitResp = $this->withSession($this->examinerSession($examiner, $timetableId))
            ->postJson('/examiner/submit-attendance', [
                'matric_no' => $matric,
                'timetable_id' => $timetableId,
                'session_id' => $session->session_id,
            ]);

        $submitResp->assertOk()
            ->assertJsonPath('success', true);

        // Attendance record should now be 'submitted'
        $this->assertDatabaseHas('attendance_records', [
            'matric_no' => $matric,
            'timetable_id' => $timetableId,
            'status' => 'submitted',
            'exit_examiner_id' => $examiner->examiner_id,
        ]);

        Carbon::setTestNow();
    }

    /**
     * Test 10: Assessment export contains only relevant assessment records.
     */
    public function test_assessment_export_contains_only_relevant_assessment_records(): void
    {
        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();
        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        $csc401Id = DB::table('timetables')
            ->where('exam_session_id', $session->session_id)
            ->where('course_code', 'CSC401')
            ->value('id');

        $cys401Id = DB::table('timetables')
            ->where('exam_session_id', $session->session_id)
            ->where('course_code', 'CYS401')
            ->value('id');

        DB::table('timetables')->where('id', $csc401Id)->update(['examiner_id' => $examiner->examiner_id]);

        $studentCSC = 'EXPORT-CSC-001';
        $studentCYS = 'EXPORT-CYS-002';
        $this->createStudent($studentCSC, (int) $session->session_id);
        $this->createStudent($studentCYS, (int) $session->session_id);

        $tokCSC = $this->createToken($studentCSC, (int) $session->session_id, (int) $csc401Id);
        $tokCYS = $this->createToken($studentCYS, (int) $session->session_id, (int) $cys401Id);

        DB::table('verification_logs')->insert([
            'token_id' => $tokCSC->token_id,
            'examiner_id' => $examiner->examiner_id,
            'decision' => 'APPROVED',
            'timestamp' => now(),
            'device_fp' => 'fp-csc',
            'ip_address' => '127.0.0.1',
            'timetable_id' => $csc401Id,
        ]);

        DB::table('verification_logs')->insert([
            'token_id' => $tokCYS->token_id,
            'examiner_id' => $examiner->examiner_id,
            'decision' => 'APPROVED',
            'timestamp' => now(),
            'device_fp' => 'fp-cys',
            'ip_address' => '127.0.0.1',
            'timetable_id' => $cys401Id,
        ]);

        // Export CSC401 report
        $response = $this->withSession($this->examinerSession($examiner, $csc401Id))
            ->get("/examiner/assessments/{$csc401Id}/export?format=csv");

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString($studentCSC, $content);
        $this->assertStringNotContainsString($studentCYS, $content);
    }

    /**
     * Test 11: Unauthorized examiner receives 403 on student profile.
     */
    public function test_unauthorized_examiner_receives_403_on_student_profile(): void
    {
        $examiner2 = $this->createExaminer('examiner_isolated');
        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        $matric = 'ISOLATED-STUDENT-001';
        $this->createStudent($matric, (int) $session->session_id);

        // Examiner2 has never scanned this student and has no timetables assigned
        $response = $this->withSession($this->examinerSession($examiner2))
            ->get("/examiner/students/{$matric}");

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createExaminer(string $username = 'examiner2'): object
    {
        $id = DB::table('examiners')->insertGetId([
            'full_name' => "Staff {$username}",
            'username' => $username,
            'password_hash' => bcrypt('password123'),
            'role' => 'examiner',
            'is_active' => true,
            'created_at' => now(),
        ]);

        return DB::table('examiners')->where('examiner_id', $id)->first();
    }

    private function createStudent(string $matric, int $sessionId): void
    {
        $dept = DB::table('departments')->first();
        DB::table('students')->updateOrInsert(
            ['matric_no' => $matric],
            [
                'full_name' => "Student {$matric}",
                'department_id' => $dept->dept_id,
                'session_id' => $sessionId,
                'level' => '400',
                'photo_path' => 'photos/test.jpg',
                'photo_status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function createToken(string $matricNo, int $sessionId, int $timetableId): object
    {
        $session = DB::table('exam_sessions')->where('session_id', $sessionId)->first();
        $student = DB::table('students')->where('matric_no', $matricNo)->first();
        $tokenId = (string) Str::uuid();

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

    private function examinerSession(object $examiner, ?int $activeTimetableId = null): array
    {
        $session = [
            'examiner_id'       => (int) $examiner->examiner_id,
            'examiner_username' => $examiner->username,
            'examiner_name'     => $examiner->full_name,
            'examiner_role'     => $examiner->role,
        ];

        if ($activeTimetableId) {
            $session['examiner_active_timetable_id'] = $activeTimetableId;
            $tt = DB::table('timetables')->where('id', $activeTimetableId)->first();
            if ($tt) {
                $session['examiner_active_timetable'] = [
                    'id'              => $tt->id,
                    'course_code'     => $tt->course_code,
                    'course_title'    => $tt->course_title,
                    'venue'           => $tt->venue,
                    'dept_name'       => 'Computer Science',
                    'level'           => $tt->level,
                    'start_time'      => $tt->start_time,
                    'end_time'        => $tt->end_time,
                    'assessment_type' => $tt->assessment_type ?? 'exam',
                ];
            }
        }

        return $session;
    }
}