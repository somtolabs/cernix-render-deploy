<?php

namespace Tests\Feature;

use App\Models\OfficialStudent;
use App\Services\MatriculationNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MatriculationAssessmentRosterWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private int $sessionId;
    private int $deptId;
    private int $examinerId;
    private int $timetableId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deptId = DB::table('departments')->insertGetId([
            'dept_name' => 'Computer Science',
            'faculty' => 'Faculty of Computing',
            'department_code' => '04',
            'faculty_code' => '04',
        ], 'dept_id');

        $this->sessionId = DB::table('exam_sessions')->insertGetId([
            'semester' => 'First Semester',
            'academic_year' => '2025/2026',
            'fee_amount' => 5000.00,
            'aes_key' => bin2hex(random_bytes(32)),
            'hmac_secret' => bin2hex(random_bytes(32)),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->examinerId = DB::table('examiners')->insertGetId([
            'username' => 'superadmin',
            'full_name' => 'Super Administrator',
            'password_hash' => bcrypt('password123'),
            'role' => 'admin',
            'is_active' => true,
            'created_at' => now(),
        ]);

        $this->timetableId = DB::table('timetables')->insertGetId([
            'exam_session_id' => $this->sessionId,
            'department_id' => $this->deptId,
            'level' => '400',
            'course_code' => 'CSC401',
            'course_title' => 'Advanced Algorithms',
            'exam_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => 'CBT Lab 1',
            'examiner_id' => $this->examinerId,
            'assessment_type' => 'exam',
            'status' => 'scheduled',
            'register_status' => 'incomplete',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seed authoritative official students in registry
        OfficialStudent::create([
            'matric_number' => '220404001',
            'normalized_matric_number' => '220404001',
            'full_name' => 'Adebayo Oluwaseun',
            'faculty' => 'Faculty of Computing',
            'department' => 'Computer Science',
            'level' => '400',
            'status' => 'active',
        ]);

        OfficialStudent::create([
            'matric_number' => 'AAUA/CSC/22/002',
            'normalized_matric_number' => 'AAUA/CSC/22/002',
            'full_name' => 'Fatima Ibrahim',
            'faculty' => 'Faculty of Computing',
            'department' => 'Computer Science',
            'level' => '400',
            'status' => 'active',
        ]);

        OfficialStudent::create([
            'matric_number' => '220404003',
            'normalized_matric_number' => '220404003',
            'full_name' => 'Chukwuemeka Okonkwo',
            'faculty' => 'Faculty of Computing',
            'department' => 'Computer Science',
            'level' => '400',
            'status' => 'active',
        ]);
    }

    private function actingAsAdmin(): self
    {
        return $this->withSession([
            'admin_logged_in' => true,
            'examiner_id' => $this->examinerId,
            'examiner_role' => 'admin',
            'examiner_name' => 'Super Administrator',
        ]);
    }

    public function test_single_column_csv_with_matric_number_imports_and_resolves_official_student_records(): void
    {
        $csvContent = "matric_number\n220404001\nAAUA/CSC/22/002\n";
        $file = UploadedFile::fake()->createWithContent('candidates.csv', $csvContent);

        $response = $this->actingAsAdmin()->post(
            route('admin.timetable.roster.import', $this->timetableId),
            ['roster_csv' => $file]
        );

        $response->assertRedirect(route('admin.timetable', ['type' => 'exam', 'edit' => $this->timetableId]));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('timetable_students', [
            'timetable_id' => $this->timetableId,
            'matric_no' => '220404001',
        ]);

        $this->assertDatabaseHas('timetable_students', [
            'timetable_id' => $this->timetableId,
            'matric_no' => 'AAUA/CSC/22/002',
        ]);

        $this->assertEquals(2, DB::table('timetable_students')->where('timetable_id', $this->timetableId)->count());

        // Assessment register must remain incomplete until Super Admin explicitly confirms it
        $this->assertDatabaseHas('timetables', [
            'id' => $this->timetableId,
            'register_status' => 'incomplete',
        ]);
    }

    public function test_candidate_csv_preview_returns_categorized_resolution(): void
    {
        // 1 matched, 1 unmatched (not in official registry), 1 duplicate, 1 invalid syntax
        $csvContent = "matric_no\n220404001\n220404001\n220404999\nINVALID_MATRIC\n";
        $file = UploadedFile::fake()->createWithContent('preview.csv', $csvContent);

        $response = $this->actingAsAdmin()->postJson(
            route('admin.timetable.roster.preview', $this->timetableId),
            ['roster_csv' => $file]
        );

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(1, $data['matched']);
        $this->assertEquals('220404001', $data['matched'][0]['matric']);
        $this->assertEquals('Adebayo Oluwaseun', $data['matched'][0]['full_name']);
        $this->assertEquals('Computer Science', $data['matched'][0]['department']);

        $this->assertCount(1, $data['unmatched']);
        $this->assertEquals('220404999', $data['unmatched'][0]['matric']);

        $this->assertCount(1, $data['duplicates']);
        $this->assertEquals('220404001', $data['duplicates'][0]['matric']);

        $this->assertCount(1, $data['invalid']);
        $this->assertEquals('INVALID_MATRIC', $data['invalid'][0]['matric']);
    }

    public function test_quick_add_single_candidate_validates_and_normalizes(): void
    {
        $response = $this->actingAsAdmin()->post(
            route('admin.timetable.roster.add', $this->timetableId),
            ['matric_no' => '  22 04 04 003  ']
        );

        $response->assertRedirect(route('admin.timetable', ['type' => 'exam', 'edit' => $this->timetableId]));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('timetable_students', [
            'timetable_id' => $this->timetableId,
            'matric_no' => '220404003',
        ]);
    }

    public function test_quick_add_single_candidate_rejects_malformed_syntax(): void
    {
        $response = $this->actingAsAdmin()->post(
            route('admin.timetable.roster.add', $this->timetableId),
            ['matric_no' => 'XYZ999']
        );

        $response->assertSessionHasErrors(['matric_no']);
        $this->assertDatabaseMissing('timetable_students', [
            'timetable_id' => $this->timetableId,
            'matric_no' => 'XYZ999',
        ]);
    }

    public function test_confirm_roster_gates_and_updates_register_status(): void
    {
        // First add candidate
        DB::table('timetable_students')->insert([
            'timetable_id' => $this->timetableId,
            'matric_no' => '220404001',
            'created_at' => now(),
        ]);

        $response = $this->actingAsAdmin()->post(
            route('admin.timetable.roster.confirm', $this->timetableId)
        );

        $response->assertRedirect(route('admin.timetable', ['type' => 'exam', 'edit' => $this->timetableId]));
        $this->assertDatabaseHas('timetables', [
            'id' => $this->timetableId,
            'register_status' => 'confirmed',
        ]);
    }

    public function test_confirm_roster_rejects_empty_candidate_list(): void
    {
        $response = $this->actingAsAdmin()->post(
            route('admin.timetable.roster.confirm', $this->timetableId)
        );

        $response->assertSessionHasErrors(['roster']);
        $this->assertDatabaseHas('timetables', [
            'id' => $this->timetableId,
            'register_status' => 'incomplete',
        ]);
    }

    public function test_exam_creation_with_csv_routes_to_preview_without_committing_roster(): void
    {
        $csvContent = "matric_number\n220404001\n";
        $file = UploadedFile::fake()->createWithContent('candidates.csv', $csvContent);

        $response = $this->actingAsAdmin()->post(route('admin.timetable.store'), [
            'exam_session_id' => $this->sessionId,
            'department_id' => $this->deptId,
            'level' => '400',
            'course_code' => 'CSC402_EXAM',
            'course_title' => 'Software Engineering',
            'exam_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'venue' => 'Hall A',
            'examiner_id' => $this->examinerId,
            'assessment_type' => 'exam',
            'status' => 'scheduled',
            'enrollment_mode' => 'csv',
            'roster_csv' => $file,
        ]);

        $created = DB::table('timetables')->where('course_code', 'CSC402_EXAM')->first();
        $this->assertNotNull($created);
        $this->assertEquals('incomplete', $created->register_status);

        $response->assertRedirect(route('admin.timetable', ['type' => 'exam', 'edit' => $created->id]));
        $response->assertSessionHas('roster_preview');
        $response->assertSessionHas('status');

        $preview = session('roster_preview');
        $this->assertCount(1, $preview['matched']);
        $this->assertEquals('220404001', $preview['matched'][0]['matric']);

        // Must NOT write candidates to database until explicitly confirmed
        $this->assertEquals(0, DB::table('timetable_students')->where('timetable_id', $created->id)->count());
    }

    public function test_test_creation_with_csv_routes_to_preview_without_committing_roster(): void
    {
        $csvContent = "matric_no\n220404001\n";
        $file = UploadedFile::fake()->createWithContent('candidates_test.csv', $csvContent);

        $response = $this->actingAsAdmin()->post(route('admin.timetable.store'), [
            'exam_session_id' => $this->sessionId,
            'department_id' => $this->deptId,
            'level' => '400',
            'course_code' => 'CSC403_TEST',
            'course_title' => 'Continuous Assessment Test',
            'exam_date' => now()->toDateString(),
            'start_time' => '13:00',
            'end_time' => '14:30',
            'venue' => 'Lab 2',
            'examiner_id' => $this->examinerId,
            'assessment_type' => 'test',
            'status' => 'scheduled',
            'enrollment_mode' => 'csv',
            'roster_csv' => $file,
        ]);

        $created = DB::table('timetables')->where('course_code', 'CSC403_TEST')->first();
        $this->assertNotNull($created);
        $this->assertEquals('test', $created->assessment_type);

        $response->assertRedirect(route('admin.timetable', ['type' => 'test', 'edit' => $created->id]));
        $response->assertSessionHas('roster_preview');

        // Verify no candidates were written silently
        $this->assertEquals(0, DB::table('timetable_students')->where('timetable_id', $created->id)->count());
    }

    public function test_makeup_creation_with_csv_routes_to_preview_without_committing_roster(): void
    {
        $csvContent = "matric\n220404001\n";
        $file = UploadedFile::fake()->createWithContent('candidates_makeup.csv', $csvContent);

        $response = $this->actingAsAdmin()->post(route('admin.timetable.store'), [
            'exam_session_id' => $this->sessionId,
            'department_id' => $this->deptId,
            'level' => '400',
            'course_code' => 'CSC404_MAKEUP',
            'course_title' => 'Make-up Exam',
            'exam_date' => now()->toDateString(),
            'start_time' => '15:00',
            'end_time' => '17:00',
            'venue' => 'Room 101',
            'examiner_id' => $this->examinerId,
            'assessment_type' => 'makeup',
            'status' => 'scheduled',
            'enrollment_mode' => 'csv',
            'roster_csv' => $file,
        ]);

        $created = DB::table('timetables')->where('course_code', 'CSC404_MAKEUP')->first();
        $this->assertNotNull($created);
        $this->assertEquals('makeup', $created->assessment_type);

        $response->assertRedirect(route('admin.timetable', ['type' => 'makeup', 'edit' => $created->id]));
        $response->assertSessionHas('roster_preview');

        // Verify no candidates were written silently
        $this->assertEquals(0, DB::table('timetable_students')->where('timetable_id', $created->id)->count());
    }

    public function test_creation_with_csv_mode_without_file_returns_validation_error(): void
    {
        $response = $this->actingAsAdmin()->post(route('admin.timetable.store'), [
            'exam_session_id' => $this->sessionId,
            'department_id' => $this->deptId,
            'level' => '400',
            'course_code' => 'CSC405_NOFILE',
            'course_title' => 'Test Without File',
            'exam_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => 'Hall B',
            'examiner_id' => $this->examinerId,
            'assessment_type' => 'exam',
            'status' => 'scheduled',
            'enrollment_mode' => 'csv',
        ]);

        $response->assertSessionHasErrors(['roster_csv']);
        $this->assertDatabaseMissing('timetables', ['course_code' => 'CSC405_NOFILE']);
    }

    public function test_cohort_population_maps_students_by_matriculation_number_intelligence(): void
    {
        // Student with null level, but matric 250404099 encodes 2025 admission (100L) and 04 dept (Computer Science)
        OfficialStudent::create([
            'matric_number'            => '250404099',
            'normalized_matric_number' => '250404099',
            'full_name'                => 'COHORT, Matric Test Student',
            'admission_year'           => 2025,
            'department_code'          => '04',
            'faculty_code'             => '04',
            'department'               => 'Computer Science',
            'level'                    => null, // Level is null in DB, mapped via matric intelligence
            'status'                   => 'active',
        ]);

        $response = $this->actingAsAdmin()->post(route('admin.timetable.store'), [
            'exam_session_id' => $this->sessionId,
            'department_id'   => $this->deptId,
            'level'           => '100',
            'course_code'     => 'CSC101_COHORT',
            'course_title'    => 'Introduction to Computing',
            'exam_date'       => now()->toDateString(),
            'start_time'      => '09:00',
            'end_time'        => '11:00',
            'venue'           => 'Hall C',
            'examiner_id'     => $this->examinerId,
            'assessment_type' => 'exam',
            'status'          => 'scheduled',
            'enrollment_mode' => 'all',
        ]);

        $response->assertRedirect();
        $created = DB::table('timetables')->where('course_code', 'CSC101_COHORT')->first();
        $this->assertNotNull($created);

        // Student was mapped to the roster using matriculation number intelligence
        $this->assertDatabaseHas('timetable_students', [
            'timetable_id' => $created->id,
            'matric_no'    => '250404099',
        ]);
    }

    public function test_load_cohort_endpoint_maps_students_via_matriculation_number(): void
    {
        $timetableId = DB::table('timetables')->insertGetId([
            'exam_session_id' => $this->sessionId,
            'department_id'   => $this->deptId,
            'level'           => '100',
            'course_code'     => 'CSC102_COHORT',
            'course_title'    => 'Data Structures',
            'exam_date'       => now()->toDateString(),
            'start_time'      => '12:00',
            'end_time'        => '14:00',
            'venue'           => 'Hall D',
            'examiner_id'     => $this->examinerId,
            'assessment_type' => 'test',
            'status'          => 'scheduled',
            'register_status' => 'incomplete',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // Student with 250404088 (Computer Science, 100 level in 2025 session)
        OfficialStudent::create([
            'matric_number'            => '250404088',
            'normalized_matric_number' => '250404088',
            'full_name'                => 'LOAD, Cohort Candidate',
            'admission_year'           => 2025,
            'department_code'          => '04',
            'level'                    => null,
            'status'                   => 'active',
        ]);

        $response = $this->actingAsAdmin()->post(route('admin.timetable.roster.populate-cohort', $timetableId));
        $response->assertRedirect();

        $this->assertDatabaseHas('timetable_students', [
            'timetable_id' => $timetableId,
            'matric_no'    => '250404088',
        ]);
    }
}
