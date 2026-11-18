<?php

namespace Tests\Feature;

use App\Models\OfficialStudent;
use App\Services\StudentRegistryImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicLevelSuggestionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('departments')->insertOrIgnore([
            ['dept_id' => 1, 'dept_name' => 'Computer Science', 'faculty' => 'Faculty of Science', 'department_code' => '04', 'faculty_code' => '04'],
            ['dept_id' => 2, 'dept_name' => 'Software Engineering', 'faculty' => 'Faculty of Science', 'department_code' => '05', 'faculty_code' => '04'],
        ]);
    }

    private function adminSession(): array
    {
        return [
            'examiner_id'       => 1,
            'examiner_role'     => 'admin',
            'examiner_username' => 'admin',
        ];
    }

    public function test_level_suggestion_formula_and_clamping(): void
    {
        /** @var StudentRegistryImportService $service */
        $service = app(StudentRegistryImportService::class);

        // Ensure session start year is 2025
        DB::table('exam_sessions')->insertOrIgnore([
            'session_id'    => 1,
            'semester'      => 'First Semester',
            'academic_year' => '2025/2026',
            'fee_amount'    => 100000.00,
            'aes_key'       => bin2hex(random_bytes(32)),
            'hmac_secret'   => bin2hex(random_bytes(32)),
            'is_active'     => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->assertEquals(2025, $service->getCurrentSessionStartYear());

        // Admitted 2025 -> (2025 - 2025 + 1) = 1 -> 100
        $this->assertEquals('100', $service->computeSuggestedLevel(2025));

        // Admitted 2024 -> (2025 - 2024 + 1) = 2 -> 200
        $this->assertEquals('200', $service->computeSuggestedLevel(2024));

        // Admitted 2023 -> (2025 - 2023 + 1) = 3 -> 300
        $this->assertEquals('300', $service->computeSuggestedLevel(2023));

        // Admitted 2022 -> (2025 - 2022 + 1) = 4 -> 400
        $this->assertEquals('400', $service->computeSuggestedLevel(2022));

        // Admitted 2021 -> (2025 - 2021 + 1) = 5 -> 500
        $this->assertEquals('500', $service->computeSuggestedLevel(2021));

        // Admitted 2018 -> (2025 - 2018 + 1) = 8 -> clamped at max 500
        $this->assertEquals('500', $service->computeSuggestedLevel(2018));

        // Admitted 2026 (future) -> (2025 - 2026 + 1) = 0 -> clamped at min 100
        $this->assertEquals('100', $service->computeSuggestedLevel(2026));

        // Undetermined / invalid admission year -> null
        $this->assertNull($service->computeSuggestedLevel(null));
        $this->assertNull($service->computeSuggestedLevel('invalid'));
        $this->assertNull($service->computeSuggestedLevel(1950));
    }

    public function test_sourcing_session_start_year_hierarchy(): void
    {
        /** @var StudentRegistryImportService $service */
        $service = app(StudentRegistryImportService::class);

        // 1. Fallback to config when no DB records exist
        DB::table('exam_sessions')->truncate();
        Config::set('academic.current_session_start_year', 2027);
        $this->assertEquals(2027, $service->getCurrentSessionStartYear());

        // 2. Active exam session overrides config
        DB::table('exam_sessions')->insert([
            'session_id'    => 10,
            'semester'      => 'First Semester',
            'academic_year' => '2028/2029',
            'fee_amount'    => 100000.00,
            'aes_key'       => bin2hex(random_bytes(32)),
            'hmac_secret'   => bin2hex(random_bytes(32)),
            'is_active'     => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        $this->assertEquals(2028, $service->getCurrentSessionStartYear());
    }

    public function test_import_preview_surfaces_suggested_level_when_level_is_blank_in_csv(): void
    {
        DB::table('exam_sessions')->insertOrIgnore([
            'session_id'    => 1,
            'semester'      => 'First Semester',
            'academic_year' => '2025/2026',
            'fee_amount'    => 100000.00,
            'aes_key'       => bin2hex(random_bytes(32)),
            'hmac_secret'   => bin2hex(random_bytes(32)),
            'is_active'     => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $csv = implode("\n", [
            'matric_number,full_name,department,faculty,level',
            '220404001,Student Without Level,Computer Science,Faculty of Science,',
            '230404002,Student With Explicit Level,Computer Science,Faculty of Science,300',
        ]);

        $response = $this
            ->withSession($this->adminSession())
            ->post(route('admin.student-registry.preview'), [
                'registry_files' => [UploadedFile::fake()->createWithContent('preview_test.csv', $csv)],
            ]);

        $response->assertOk();
        $data = $response->json('data');

        $this->assertNotEmpty($data['preview_rows']);
        $row1 = $data['preview_rows'][0];
        $row2 = $data['preview_rows'][1];

        // Row 1: Blank level in CSV -> Suggested 400
        $this->assertEquals('220404001', $row1['matric_number']);
        $this->assertEquals('400', $row1['level']);
        $this->assertTrue($row1['is_level_suggested']);
        $this->assertEquals('400', $row1['suggested_level']);
        $this->assertEquals('suggested', $row1['level_source']);

        // Row 2: Explicit 300 level in CSV -> Not suggested
        $this->assertEquals('230404002', $row2['matric_number']);
        $this->assertEquals('300', $row2['level']);
        $this->assertFalse($row2['is_level_suggested']);
        $this->assertNull($row2['suggested_level']);
        $this->assertEquals('csv', $row2['level_source']);
    }

    public function test_import_assigns_suggested_level_to_new_student_without_level(): void
    {
        DB::table('exam_sessions')->insertOrIgnore([
            'session_id'    => 1,
            'semester'      => 'First Semester',
            'academic_year' => '2025/2026',
            'fee_amount'    => 100000.00,
            'aes_key'       => bin2hex(random_bytes(32)),
            'hmac_secret'   => bin2hex(random_bytes(32)),
            'is_active'     => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $csv = implode("\n", [
            'matric_number,full_name',
            '220404099,Admitted 2022 Student',
        ]);

        $response = $this
            ->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('new_student.csv', $csv),
            ]);

        $response->assertRedirect(route('admin.student-registry'));

        $student = OfficialStudent::where('matric_number', '220404099')->first();
        $this->assertNotNull($student);
        $this->assertEquals('400', $student->level);
    }

    public function test_import_never_overwrites_existing_non_null_level(): void
    {
        DB::table('exam_sessions')->insertOrIgnore([
            'session_id'    => 1,
            'semester'      => 'First Semester',
            'academic_year' => '2025/2026',
            'fee_amount'    => 100000.00,
            'aes_key'       => bin2hex(random_bytes(32)),
            'hmac_secret'   => bin2hex(random_bytes(32)),
            'is_active'     => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Pre-create student with an existing 200 level (e.g. repeated a year or special track)
        OfficialStudent::create([
            'matric_number'            => '220404050',
            'normalized_matric_number' => '220404050',
            'full_name'                => 'EZE, Chiamaka Ruth',
            'admission_year'           => 2022,
            'level'                    => '200', // Existing non-null level
            'department'               => 'Computer Science',
            'faculty'                  => 'Faculty of Science',
            'status'                   => 'active',
        ]);

        // Incoming CSV has matching name and BLANK level
        $csv = implode("\n", [
            'matric_number,full_name,department,level',
            '220404050,EZE, Chiamaka Ruth,Computer Science,',
        ]);

        $response = $this
            ->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('update_no_level.csv', $csv),
            ]);

        $response->assertRedirect(route('admin.student-registry'));

        $student = OfficialStudent::where('matric_number', '220404050')->first();
        // NON-OVERWRITE GUARANTEE: Must remain 200, NOT overwritten by 400!
        $this->assertEquals('200', $student->level);
    }

    public function test_manual_registration_supports_auto_suggest_explicit_level_and_unassigned(): void
    {
        DB::table('exam_sessions')->insertOrIgnore([
            'session_id'    => 1,
            'semester'      => 'First Semester',
            'academic_year' => '2025/2026',
            'fee_amount'    => 100000.00,
            'aes_key'       => bin2hex(random_bytes(32)),
            'hmac_secret'   => bin2hex(random_bytes(32)),
            'is_active'     => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // 1. Omitted level -> Auto-suggested (2022 -> 400)
        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.manual'), [
                'matric_number' => '220404101',
                'full_name'     => 'STUDENT, Auto One',
            ])->assertRedirect();

        $student1 = OfficialStudent::where('matric_number', '220404101')->first();
        $this->assertEquals('400', $student1->level);

        // 2. Explicit level (e.g. 100) -> Respected
        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.manual'), [
                'matric_number' => '220404102',
                'full_name'     => 'STUDENT, Explicit Two',
                'level'         => '100',
            ])->assertRedirect();

        $student2 = OfficialStudent::where('matric_number', '220404102')->first();
        $this->assertEquals('100', $student2->level);

        // 3. 'unassigned' -> Level is null
        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.manual'), [
                'matric_number' => '220404103',
                'full_name'     => 'STUDENT, Unassigned Three',
                'level'         => 'unassigned',
            ])->assertRedirect();

        $student3 = OfficialStudent::where('matric_number', '220404103')->first();
        $this->assertNull($student3->level);
    }
}
