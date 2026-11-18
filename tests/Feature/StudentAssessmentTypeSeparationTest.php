<?php

namespace Tests\Feature;

use App\Services\AssessmentTimeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentAssessmentTypeSeparationTest extends TestCase
{
    use RefreshDatabase;

    private int $sessionId;
    private int $deptId;
    private int $examTimetableId;
    private int $testTimetableId;
    private int $makeupTimetableId;
    private int $makeUpAltTimetableId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');

        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        $this->sessionId = (int) $session->session_id;

        $dept = DB::table('departments')->first();
        $this->deptId = (int) $dept->dept_id;

        $today = Carbon::today('Africa/Lagos')->toDateString();

        // Ensure CSC401 is an exam
        DB::table('timetables')->where('course_code', 'CSC401')->update([
            'assessment_type' => 'exam',
            'department_id'   => $this->deptId,
            'level'           => '400',
            'exam_date'       => $today,
            'start_time'      => '09:00',
            'end_time'        => '12:00',
            'register_status' => 'confirmed',
        ]);
        $this->examTimetableId = (int) DB::table('timetables')->where('course_code', 'CSC401')->value('id');

        // Create an in-semester test
        $this->testTimetableId = (int) DB::table('timetables')->insertGetId([
            'exam_session_id' => $this->sessionId,
            'department_id'   => $this->deptId,
            'course_code'     => 'CSC405',
            'course_title'    => 'Software Engineering Test',
            'level'           => '400',
            'exam_date'       => $today,
            'start_time'      => '13:00',
            'end_time'        => '14:30',
            'venue'           => 'Lab 3',
            'assessment_type' => 'test',
            'status'          => 'scheduled',
            'register_status' => 'confirmed',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // Create a make-up assessment with 'makeup'
        $this->makeupTimetableId = (int) DB::table('timetables')->insertGetId([
            'exam_session_id' => $this->sessionId,
            'department_id'   => $this->deptId,
            'course_code'     => 'CSC409',
            'course_title'    => 'Computer Networks Make-up',
            'level'           => '400',
            'exam_date'       => $today,
            'start_time'      => '15:00',
            'end_time'        => '17:00',
            'venue'           => 'Main Hall A',
            'assessment_type' => 'makeup',
            'status'          => 'scheduled',
            'register_status' => 'confirmed',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // Create an alternate make-up assessment with 'make_up'
        $this->makeUpAltTimetableId = (int) DB::table('timetables')->insertGetId([
            'exam_session_id' => $this->sessionId,
            'department_id'   => $this->deptId,
            'course_code'     => 'CSC411',
            'course_title'    => 'Distributed Systems Supplementary',
            'level'           => '400',
            'exam_date'       => $today,
            'start_time'      => '17:30',
            'end_time'        => '19:00',
            'venue'           => 'Room 102',
            'assessment_type' => 'make_up',
            'status'          => 'scheduled',
            'register_status' => 'confirmed',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Test 1: Student timetable default (/student/timetable) shows ONLY regular examinations.
     */
    public function test_student_timetable_default_shows_only_regular_examinations(): void
    {
        $this->registerStudent('220404008');

        $response = $this->get('/student/timetable');

        $response->assertOk()
            ->assertSee('CSC401')
            ->assertSee('Regular Examinations')
            ->assertDontSee('CSC405')
            ->assertDontSee('Software Engineering Test')
            ->assertDontSee('CSC409')
            ->assertDontSee('CSC411');
    }

    /**
     * Test 2: Student timetable with type=exam shows ONLY regular examinations.
     */
    public function test_student_timetable_exam_filter_shows_only_regular_examinations(): void
    {
        $this->registerStudent('220404008');

        $response = $this->get('/student/timetable?type=exam');

        $response->assertOk()
            ->assertSee('CSC401')
            ->assertSee('Regular Examinations')
            ->assertDontSee('CSC405')
            ->assertDontSee('CSC409')
            ->assertDontSee('CSC411');
    }

    /**
     * Test 3: Student timetable with type=test shows ONLY tests.
     */
    public function test_student_timetable_test_filter_shows_only_tests(): void
    {
        $this->registerStudent('220404008');

        $response = $this->get('/student/timetable?type=test');

        $response->assertOk()
            ->assertSee('CSC405')
            ->assertSee('Software Engineering Test')
            ->assertSee('In-Semester Tests')
            ->assertDontSee('CSC401')
            ->assertDontSee('CSC409')
            ->assertDontSee('CSC411');
    }

    /**
     * Test 4: Student timetable with type=makeup shows ONLY make-up assessments (including make_up).
     */
    public function test_student_timetable_makeup_filter_shows_only_makeups(): void
    {
        $this->registerStudent('220404008');

        $response = $this->get('/student/timetable?type=makeup');

        $response->assertOk()
            ->assertSee('CSC409')
            ->assertSee('CSC411')
            ->assertSee('Make-up Examinations')
            ->assertDontSee('CSC401')
            ->assertDontSee('CSC405');
    }

    /**
     * Test 5: Student timetable with type=make_up alias also resolves to make-up assessments.
     */
    public function test_student_timetable_make_up_alias_resolves_correctly(): void
    {
        $this->registerStudent('220404008');

        $response = $this->get('/student/timetable?type=make_up');

        $response->assertOk()
            ->assertSee('CSC409')
            ->assertSee('CSC411')
            ->assertDontSee('CSC401')
            ->assertDontSee('CSC405');
    }

    /**
     * Test 6: Student dashboard categorizes upcoming assessments into separate groups.
     */
    public function test_student_dashboard_groups_upcoming_assessments_by_type(): void
    {
        $today = Carbon::today('Africa/Lagos')->toDateString();
        Carbon::setTestNow(Carbon::parse("{$today} 08:00:00", 'Africa/Lagos'));

        $this->registerStudent('220404008');

        $response = $this->get('/student/dashboard');

        $response->assertOk()
            ->assertSee('Upcoming Regular Examinations')
            ->assertSee('Upcoming In-Semester Tests')
            ->assertSee('Upcoming Make-up Examinations')
            ->assertSee('CSC401')
            ->assertSee('CSC405')
            ->assertSee('CSC409')
            ->assertSee('CSC411');
    }

    /**
     * Test 7: Student portal sidebar navigation has separated badges for exams, tests, and makeups.
     */
    public function test_student_portal_sidebar_has_separated_navigation_badges(): void
    {
        $this->registerStudent('220404008');

        $response = $this->get('/student/dashboard');

        $response->assertOk()
            ->assertSee('Examinations')
            ->assertSee('Tests')
            ->assertSee('Make-up Exams');
    }

    /**
     * Test 8: Examiner Start Scanning via standard form POST redirects directly to dashboard with scanner active.
     */
    public function test_examiner_start_scanning_form_post_redirects_to_scanner(): void
    {
        $today = Carbon::today('Africa/Lagos')->toDateString();
        Carbon::setTestNow(Carbon::parse("{$today} 09:30:00", 'Africa/Lagos'));

        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();
        DB::table('timetables')->where('id', $this->examTimetableId)->update([
            'examiner_id' => $examiner->examiner_id,
        ]);

        $response = $this->withSession($this->examinerSession($examiner))
            ->post('/examiner/scan-session/start', [
                '_token'       => 'test-token',
                'timetable_id' => $this->examTimetableId,
            ]);

        // Must redirect to dashboard with autostart=1, NOT return raw JSON
        $response->assertRedirect(route('examiner.dashboard', ['autostart' => 1]));
        $response->assertSessionHas('status', 'Scanning session started for CSC401.');
        $response->assertSessionHas('examiner_active_timetable_id', $this->examTimetableId);

        // Follow redirect to dashboard
        $dashResponse = $this->withSession($this->examinerSession($examiner))
            ->get(route('examiner.dashboard', ['autostart' => 1]));

        $dashResponse->assertOk()
            ->assertSee('Session Active')
            ->assertSee('CSC401')
            ->assertSee('In Progress: Regular Entry &amp; Departure', false);
    }

    /**
     * Test 9: Examiner Start Scanning via JSON request returns redirect URL and success.
     */
    public function test_examiner_start_scanning_json_returns_redirect_url(): void
    {
        $today = Carbon::today('Africa/Lagos')->toDateString();
        Carbon::setTestNow(Carbon::parse("{$today} 09:30:00", 'Africa/Lagos'));

        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();
        DB::table('timetables')->where('id', $this->examTimetableId)->update([
            'examiner_id' => $examiner->examiner_id,
        ]);

        $response = $this->withSession($this->examinerSession($examiner))
            ->postJson('/examiner/scan-session/start', [
                'timetable_id' => $this->examTimetableId,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('redirect', route('examiner.dashboard', ['autostart' => 1]))
            ->assertJsonPath('exam.course_code', 'CSC401');
    }

    /**
     * Test 10: Examiner Start Scanning outside permitted time window redirects back with error on form POST.
     */
    public function test_examiner_start_scanning_outside_window_form_post_redirects_back(): void
    {
        $today = Carbon::today('Africa/Lagos')->toDateString();
        // 8:00 AM is before 8:30 AM early scanning window
        Carbon::setTestNow(Carbon::parse("{$today} 08:00:00", 'Africa/Lagos'));

        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();

        $response = $this->withSession($this->examinerSession($examiner))
            ->from(route('examiner.assessments.show', $this->examTimetableId))
            ->post('/examiner/scan-session/start', [
                '_token'       => 'test-token',
                'timetable_id' => $this->examTimetableId,
            ]);

        $response->assertRedirect(route('examiner.assessments.show', $this->examTimetableId));
        $response->assertSessionHas('error');
    }

    /**
     * Test 11: Examiner Stop Scanning via form POST redirects back with status.
     */
    public function test_examiner_stop_scanning_form_post_redirects_back(): void
    {
        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();

        $response = $this->withSession(array_merge($this->examinerSession($examiner), [
            'examiner_active_timetable_id' => $this->examTimetableId,
            'examiner_active_timetable'    => ['id' => $this->examTimetableId, 'course_code' => 'CSC401'],
        ]))
            ->from(route('examiner.assessments.show', $this->examTimetableId))
            ->post('/examiner/scan-session/stop', [
                '_token' => 'test-token',
            ]);

        $response->assertRedirect(route('examiner.assessments.show', $this->examTimetableId));
        $response->assertSessionHas('status', 'Active scanning session ended.');
        $response->assertSessionMissing('examiner_active_timetable_id');
    }

    /**
     * Test 12: Generate exam pass page displays assessment type tags for each course.
     */
    public function test_generate_exam_pass_page_distinguishes_assessment_types(): void
    {
        $this->registerStudent('220404008');

        $response = $this->get('/student/generate-exam-pass');

        $response->assertOk()
            ->assertSee('CSC401')
            ->assertSee('CSC405')
            ->assertSee('CSC409')
            ->assertSee('CSC411')
            ->assertSee('Test')
            ->assertSee('Make-up');
    }

    /**
     * Test 13: Quick generate pass allows test pass generation without requiring payment.
     */
    public function test_test_and_makeup_passes_do_not_require_payment(): void
    {
        $this->registerStudent('220404008');

        // Student has NOT paid school fees.
        // Quick generate for test CSC405 should succeed without payment error.
        $response = $this->post(route('student.pass.quick-generate', ['timetable' => $this->testTimetableId]));

        $response->assertRedirect(route('student.exam-access-id.course', ['timetable' => $this->testTimetableId]));

        $this->assertDatabaseHas('qr_tokens', [
            'student_id'   => '220404008',
            'timetable_id' => $this->testTimetableId,
        ]);
    }

    /**
     * Test 14: Scan detail displays assessment course code, title, and normalized type.
     */
    public function test_scan_detail_displays_assessment_course_and_type(): void
    {
        $this->registerStudent('220404008');

        $this->post(route('student.pass.quick-generate', ['timetable' => $this->testTimetableId]));

        $token = DB::table('qr_tokens')
            ->where('student_id', '220404008')
            ->where('timetable_id', $this->testTimetableId)
            ->first();

        $this->assertNotNull($token);

        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();

        $logId = DB::table('verification_logs')->insertGetId([
            'token_id'            => $token->token_id,
            'examiner_id'         => $examiner->examiner_id,
            'timetable_id'        => $this->testTimetableId,
            'decision'            => 'APPROVED',
            'scan_classification' => 'regular_scan',
            'timestamp'           => now(),
            'device_fp'           => 'test-device',
            'ip_address'          => '127.0.0.1',
        ]);

        $response = $this->get("/student/scans/{$logId}");

        $response->assertOk()
            ->assertSee('CSC405')
            ->assertSee('Software Engineering Test')
            ->assertSee('In-Semester Test');
    }

    /**
     * Helper: register a demo student and set photo status approved.
     */
    private function registerStudent(string $matricNo): void
    {
        DB::table('official_students')->updateOrInsert(
            ['matric_number' => $matricNo],
            [
                'full_name'        => 'Ada Lovelace',
                'department'       => 'Computer Science',
                'faculty'          => 'Computing',
                'level'            => '400',
                'programme'        => 'BSc Computer Science',
                'academic_session' => '2025/2026',
                'status'           => 'active',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]
        );

        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/student/register', [
                'matric_no'      => $matricNo,
                'passport_photo' => UploadedFile::fake()->image('passport.jpg', 240, 320),
            ])->assertOk();

        DB::table('students')->where('matric_no', $matricNo)->update([
            'photo_status' => 'approved',
        ]);

        foreach ([$this->examTimetableId, $this->testTimetableId, $this->makeupTimetableId, $this->makeUpAltTimetableId] as $ttId) {
            DB::table('timetable_students')->insertOrIgnore([
                'timetable_id' => $ttId,
                'matric_no' => $matricNo,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Helper: build examiner session array.
     */
    private function examinerSession(object $examiner): array
    {
        return [
            'examiner_id'       => $examiner->examiner_id,
            'examiner_username' => $examiner->username,
            'examiner_name'     => $examiner->full_name,
            'examiner_role'     => $examiner->role,
        ];
    }
}
