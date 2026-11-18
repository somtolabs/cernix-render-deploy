<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminTimetableMandatoryEndTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test 1: Timetable creation rejects request when end_time is missing.
     */
    public function test_admin_timetable_store_rejects_missing_end_time(): void
    {
        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        $dept = DB::table('departments')->first();

        $payload = [
            'exam_session_id' => $session->session_id,
            'department_id'   => $dept->dept_id,
            'level'           => '100',
            'course_code'     => 'TEST101',
            'course_title'    => 'Introduction to Testing',
            'exam_date'       => Carbon::today()->addDays(5)->toDateString(),
            'start_time'      => '09:00',
            // 'end_time' omitted intentionally
            'venue'           => 'Hall Alpha',
            'assessment_type' => 'exam',
        ];

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.timetable.store'), $payload);

        $response->assertSessionHasErrors(['end_time']);
        $this->assertDatabaseMissing('timetables', [
            'course_code' => 'TEST101',
        ]);
    }

    /**
     * Test 2: Timetable creation rejects request when end_time is not after start_time.
     */
    public function test_admin_timetable_store_rejects_end_time_not_after_start_time(): void
    {
        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        $dept = DB::table('departments')->first();

        // Case A: end_time equal to start_time
        $responseEqual = $this->withSession($this->adminSession())
            ->post(route('admin.timetable.store'), [
                'exam_session_id' => $session->session_id,
                'department_id'   => $dept->dept_id,
                'level'           => '100',
                'course_code'     => 'TEST102',
                'course_title'    => 'Boundary Testing Equal',
                'exam_date'       => Carbon::today()->addDays(5)->toDateString(),
                'start_time'      => '10:00',
                'end_time'        => '10:00',
                'venue'           => 'Hall Alpha',
                'assessment_type' => 'exam',
            ]);

        $responseEqual->assertSessionHasErrors(['end_time']);

        // Case B: end_time before start_time
        $responseBefore = $this->withSession($this->adminSession())
            ->post(route('admin.timetable.store'), [
                'exam_session_id' => $session->session_id,
                'department_id'   => $dept->dept_id,
                'level'           => '100',
                'course_code'     => 'TEST103',
                'course_title'    => 'Boundary Testing Before',
                'exam_date'       => Carbon::today()->addDays(5)->toDateString(),
                'start_time'      => '14:00',
                'end_time'        => '12:00',
                'venue'           => 'Hall Alpha',
                'assessment_type' => 'exam',
            ]);

        $responseBefore->assertSessionHasErrors(['end_time']);
    }

    /**
     * Test 3: Timetable creation successfully persists record when valid end_time is supplied.
     */
    public function test_admin_timetable_store_persists_valid_schedule_with_end_time(): void
    {
        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        $dept = DB::table('departments')->first();
        $examiner = DB::table('examiners')->where('is_active', true)->first();

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.timetable.store'), [
                'exam_session_id' => $session->session_id,
                'department_id'   => $dept->dept_id,
                'level'           => '200',
                'course_code'     => 'VALID201',
                'course_title'    => 'Valid Assessment Scheduling',
                'exam_date'       => Carbon::today()->addDays(5)->toDateString(),
                'start_time'      => '09:00',
                'end_time'        => '12:00',
                'venue'           => 'Main Auditorium',
                'assessment_type' => 'exam',
                'status'          => 'scheduled',
                'examiner_id'     => $examiner->examiner_id,
            ]);

        $response->assertRedirect(route('admin.timetable', ['type' => 'exam']));
        $this->assertDatabaseHas('timetables', [
            'course_code' => 'VALID201',
            'start_time'  => '09:00',
            'end_time'    => '12:00',
        ]);
    }

    /**
     * Test 4: Timetable update rejects removing end_time or setting it before start_time.
     */
    public function test_admin_timetable_update_rejects_invalid_end_time(): void
    {
        $timetable = DB::table('timetables')->where('course_code', 'CSC401')->first();
        $this->assertNotNull($timetable);

        // Attempting to clear end_time
        $responseClear = $this->withSession($this->adminSession())
            ->put(route('admin.timetable.update', $timetable->id), [
                'exam_session_id' => $timetable->exam_session_id,
                'department_id'   => $timetable->department_id,
                'level'           => $timetable->level,
                'course_code'     => $timetable->course_code,
                'course_title'    => $timetable->course_title,
                'exam_date'       => $timetable->exam_date,
                'start_time'      => '09:00',
                'end_time'        => '',
                'venue'           => $timetable->venue,
                'assessment_type' => 'exam',
                'status'          => 'scheduled',
                'examiner_id'     => $timetable->examiner_id ?? 1,
            ]);

        $responseClear->assertSessionHasErrors(['end_time']);

        // Attempting to set end_time before start_time
        $responseEarly = $this->withSession($this->adminSession())
            ->put(route('admin.timetable.update', $timetable->id), [
                'exam_session_id' => $timetable->exam_session_id,
                'department_id'   => $timetable->department_id,
                'level'           => $timetable->level,
                'course_code'     => $timetable->course_code,
                'course_title'    => $timetable->course_title,
                'exam_date'       => $timetable->exam_date,
                'start_time'      => '11:00',
                'end_time'        => '09:00',
                'venue'           => $timetable->venue,
                'assessment_type' => 'exam',
                'status'          => 'scheduled',
                'examiner_id'     => $timetable->examiner_id ?? 1,
            ]);

        $responseEarly->assertSessionHasErrors(['end_time']);
    }

    /**
     * Test 5: Timetable CSV import rejects rows with missing end_time.
     */
    public function test_admin_csv_import_rejects_row_with_missing_end_time(): void
    {
        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        $examiner = DB::table('examiners')->where('is_active', true)->first();

        $csvContent = implode("\n", [
            'course_code,course_title,department,level,exam_date,start_time,end_time,venue',
            'IMP101,Import Course One,Computer Science,100,2026-12-01,09:00,,Hall A',
        ]);

        $file = UploadedFile::fake()->createWithContent('timetable_missing_end.csv', $csvContent);

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.timetable.import'), [
                'exam_session_id'      => $session->session_id,
                'override_examiner_id' => $examiner->examiner_id,
                'csv_file'             => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $statusMessage = session('status');

        $this->assertStringContainsString('Row 2', $statusMessage);
        $this->assertStringContainsString('end_time', strtolower($statusMessage));

        $this->assertDatabaseMissing('timetables', [
            'course_code' => 'IMP101',
        ]);
    }

    /**
     * Test 6: Timetable CSV import rejects rows where end_time is not later than start_time.
     */
    public function test_admin_csv_import_rejects_row_where_end_time_not_after_start_time(): void
    {
        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        $examiner = DB::table('examiners')->where('is_active', true)->first();

        $csvContent = implode("\n", [
            'course_code,course_title,department,level,exam_date,start_time,end_time,venue',
            'IMP102,Import Invalid End,Computer Science,100,2026-12-01,11:00,10:00,Hall A',
        ]);

        $file = UploadedFile::fake()->createWithContent('timetable_invalid_time.csv', $csvContent);

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.timetable.import'), [
                'exam_session_id'      => $session->session_id,
                'override_examiner_id' => $examiner->examiner_id,
                'csv_file'             => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $statusMessage = session('status');

        $this->assertStringContainsString('Row 2', $statusMessage);
        $this->assertStringContainsString('later than start_time', strtolower($statusMessage));

        $this->assertDatabaseMissing('timetables', [
            'course_code' => 'IMP102',
        ]);
    }

    /**
     * Test 7: Timetable CSV import successfully imports valid rows with start_time and end_time.
     */
    public function test_admin_csv_import_accepts_valid_rows_with_end_time(): void
    {
        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        $examiner = DB::table('examiners')->where('is_active', true)->first();

        $csvContent = implode("\n", [
            'course_code,course_title,department,level,exam_date,start_time,end_time,venue',
            'IMP201,Valid Bulk Assessment,Computer Science,200,2026-12-01,09:00,12:00,Main Hall',
        ]);

        $file = UploadedFile::fake()->createWithContent('timetable_valid.csv', $csvContent);

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.timetable.import'), [
                'exam_session_id'      => $session->session_id,
                'override_examiner_id' => $examiner->examiner_id,
                'csv_file'             => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('timetables', [
            'course_code' => 'IMP201',
            'start_time'  => '09:00',
            'end_time'    => '12:00',
        ]);
    }

    /**
     * Test 8: Timetable index page warns when missing end_time records exist.
     */
    public function test_admin_timetable_index_displays_warning_for_missing_end_time(): void
    {
        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        $dept = DB::table('departments')->first();

        // Insert legacy or incomplete timetable directly
        DB::table('timetables')->insert([
            'exam_session_id' => $session->session_id,
            'department_id'   => $dept->dept_id,
            'level'           => '300',
            'course_code'     => 'INCOMPL301',
            'course_title'    => 'Incomplete Schedule Assessment',
            'exam_date'       => Carbon::today()->toDateString(),
            'start_time'      => '09:00',
            'end_time'        => null,
            'venue'           => 'Hall B',
            'status'          => 'scheduled',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.timetable'));

        $response->assertOk();
        $response->assertSee('Action Required:');
        $response->assertSee('have no end time recorded');
        $response->assertSee('INCOMPL301');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function adminSession(): array
    {
        $admin = DB::table('examiners')
            ->whereIn('role', ['admin', 'superadmin', 'ADMIN', 'SUPERADMIN'])
            ->first();

        return [
            'examiner_id'       => (int) $admin->examiner_id,
            'examiner_username' => $admin->username,
            'examiner_name'     => $admin->full_name,
            'examiner_role'     => $admin->role,
        ];
    }
}
