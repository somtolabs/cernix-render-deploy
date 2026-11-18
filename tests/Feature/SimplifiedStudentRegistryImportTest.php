<?php

namespace Tests\Feature;

use App\Models\OfficialStudent;
use App\Services\StudentRegistryImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SimplifiedStudentRegistryImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed core computing departments with codes for institutional mapping tests
        DB::table('departments')->insertOrIgnore([
            ['dept_id' => 1, 'dept_name' => 'Computer Science',       'faculty' => 'Faculty of Computing', 'department_code' => '04', 'faculty_code' => '04'],
            ['dept_id' => 2, 'dept_name' => 'Software Engineering',   'faculty' => 'Faculty of Computing', 'department_code' => '05', 'faculty_code' => '04'],
            ['dept_id' => 3, 'dept_name' => 'Information Technology', 'faculty' => 'Faculty of Computing', 'department_code' => '06', 'faculty_code' => '04'],
            ['dept_id' => 4, 'dept_name' => 'Cyber Security',         'faculty' => 'Faculty of Computing', 'department_code' => '07', 'faculty_code' => '04'],
            ['dept_id' => 5, 'dept_name' => 'Data Science',           'faculty' => 'Faculty of Computing', 'department_code' => '08', 'faculty_code' => '04'],
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

    public function test_two_column_csv_import_succeeds_and_auto_derives_faculty_and_department(): void
    {
        $csv = implode("\n", [
            'matric_number,full_name',
            '250404001,Adeyemi Oluwaseun Miracle',
            '240405002,Okonkwo Chidinma Peace',
        ]);

        $response = $this
            ->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('students.csv', $csv),
            ]);

        $response->assertRedirect(route('admin.student-registry'));
        $response->assertSessionHas('status');

        $this->assertDatabaseCount('official_students', 2);

        // Student 1: Computer Science (04/04)
        $this->assertDatabaseHas('official_students', [
            'matric_number'   => '250404001',
            'full_name'        => 'Adeyemi Oluwaseun Miracle',
            'admission_year'   => 2025,
            'faculty_code'     => '04',
            'department_code'  => '04',
            'department'       => 'Computer Science',
            'faculty'          => 'Faculty of Computing',
            'status'           => 'active',
        ]);

        // Student 2: Software Engineering (04/05)
        $this->assertDatabaseHas('official_students', [
            'matric_number'   => '240405002',
            'full_name'        => 'Okonkwo Chidinma Peace',
            'admission_year'   => 2024,
            'faculty_code'     => '04',
            'department_code'  => '05',
            'department'       => 'Software Engineering',
            'faculty'          => 'Faculty of Computing',
            'status'           => 'active',
        ]);

        // Level is computed from admission year (2025 in session 2025/2026 -> 100 level)
        $student1 = DB::table('official_students')->where('matric_number', '250404001')->first();
        $this->assertEquals('100', $student1->level);

        $student2 = DB::table('official_students')->where('matric_number', '240405002')->first();
        $this->assertEquals('200', $student2->level);
    }

    public function test_existing_full_column_csv_import_remains_fully_backward_compatible(): void
    {
        $csv = implode("\n", [
            'matric_number,full_name,department,faculty,level,programme,academic_session,status',
            '220404008,Adebayo Oluwaseun Emmanuel,Computer Science,Faculty of Computing,400,BSc Computer Science,2025/2026,active',
        ]);

        $response = $this
            ->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('full_students.csv', $csv),
            ]);

        $response->assertRedirect(route('admin.student-registry'));

        $this->assertDatabaseHas('official_students', [
            'matric_number'    => '220404008',
            'full_name'        => 'Adebayo Oluwaseun Emmanuel',
            'department'       => 'Computer Science',
            'faculty'          => 'Faculty of Computing',
            'level'            => '400',
            'programme'        => 'BSc Computer Science',
            'academic_session' => '2025/2026',
            'status'           => 'active',
        ]);
    }

    public function test_missing_institutional_mapping_imports_with_null_department_and_faculty(): void
    {
        // Faculty 99, Department 99: unmapped codes
        $csv = implode("\n", [
            'matric_number,full_name',
            '259999001,Unmapped Student',
        ]);

        $response = $this
            ->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('unmapped.csv', $csv),
            ]);

        $response->assertRedirect(route('admin.student-registry'));

        $this->assertDatabaseHas('official_students', [
            'matric_number'   => '259999001',
            'full_name'       => 'Unmapped Student',
            'admission_year'  => 2025,
            'faculty_code'    => '99',
            'department_code' => '99',
            'department'      => null,
            'faculty'         => null,
        ]);
    }

    public function test_in_file_duplicate_matriculation_numbers_rejected_with_diagnostic_error(): void
    {
        $csv = implode("\n", [
            'matric_number,full_name',
            '250404001,First Entry',
            '250404001,Duplicate Entry in File',
            '250404002,Second Student',
        ]);

        $response = $this
            ->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('dupes.csv', $csv),
            ]);

        $response->assertRedirect(route('admin.student-registry'));

        // Only 2 distinct students imported
        $this->assertDatabaseCount('official_students', 2);
        $this->assertDatabaseHas('official_students', ['matric_number' => '250404001', 'full_name' => 'First Entry']);
        $this->assertDatabaseHas('official_students', ['matric_number' => '250404002']);

        // Check import record logged the duplicate row failure
        $importRecord = DB::table('student_registry_imports')->latest('id')->first();
        $this->assertSame(3, $importRecord->total_rows);
        $this->assertSame(2, $importRecord->imported_rows);
        $this->assertSame(1, $importRecord->failed_rows);

        $summary = json_decode($importRecord->error_summary, true);
        $this->assertStringContainsString('Duplicate matriculation number in upload', $summary['errors'][0]['reason']);
    }

    public function test_existing_student_detection_updates_attributes_without_creating_duplicates(): void
    {
        DB::table('official_students')->insert([
            'matric_number' => '250404001',
            'full_name'     => 'Original Name',
            'department'    => 'Computer Science',
            'faculty'       => 'Faculty of Computing',
            'level'         => '100',
            'status'        => 'active',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $csv = implode("\n", [
            'matric_number,full_name,level',
            '250404001,Original Name,200',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('update.csv', $csv),
            ]);

        // Still only 1 record
        $this->assertDatabaseCount('official_students', 1);
        $this->assertDatabaseHas('official_students', [
            'matric_number' => '250404001',
            'full_name'     => 'Original Name',
            'level'         => '200',
        ]);

        $import = DB::table('student_registry_imports')->latest('id')->first();
        $summary = json_decode($import->error_summary, true);
        $this->assertSame(1, $summary['updated_records']);
        $this->assertSame(1, $summary['conflict_count']);
    }

    public function test_conflicting_supplied_department_logs_inconsistency_without_corrupting_identity(): void
    {
        // 250404001 decodes to Computer Science (dept 04), but CSV supplies "Biochemistry"
        $csv = implode("\n", [
            'matric_number,full_name,department',
            '250404001,Adeyemi Oluwaseun,Biochemistry',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('conflict.csv', $csv),
            ]);

        $this->assertDatabaseHas('official_students', [
            'matric_number' => '250404001',
            'department'    => 'Biochemistry',
        ]);

        $import = DB::table('student_registry_imports')->latest('id')->first();
        $summary = json_decode($import->error_summary, true);
        $this->assertNotEmpty($summary['conflicts']);
        $this->assertSame('supplied_department_inconsistency', $summary['conflicts'][0]['type']);
        $this->assertStringContainsString('Biochemistry', $summary['conflicts'][0]['note']);
    }

    public function test_invalid_matriculation_number_is_rejected_and_logged(): void
    {
        $csv = implode("\n", [
            'matric_number,full_name',
            '25040401,Wrong Length Student', // 8 digits
            '250404001,Valid Student',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('invalid.csv', $csv),
            ]);

        $this->assertDatabaseCount('official_students', 1);
        $this->assertDatabaseHas('official_students', ['matric_number' => '250404001']);
        $this->assertDatabaseMissing('official_students', ['matric_number' => '25040401']);

        $import = DB::table('student_registry_imports')->latest('id')->first();
        $this->assertSame(1, $import->failed_rows);
        $this->assertSame(1, $import->imported_rows);
    }

    public function test_historical_slash_format_imports_and_derives_department_via_prefix(): void
    {
        $csv = implode("\n", [
            'matric_number,full_name',
            'CSC/2021/001,Adebayo Oluwaseun',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('slash.csv', $csv),
            ]);

        $this->assertDatabaseHas('official_students', [
            'matric_number' => 'CSC/2021/001',
            'full_name'     => 'Adebayo Oluwaseun',
            'department'    => 'Computer Science',
            'faculty'       => 'Faculty of Computing',
            'admission_year'=> 2021,
        ]);
    }

    public function test_missing_full_name_is_rejected(): void
    {
        $csv = implode("\n", [
            'matric_number,full_name',
            '250404001,', // Empty name
            '250404002,Valid Name',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('noname.csv', $csv),
            ]);

        $this->assertDatabaseCount('official_students', 1);
        $this->assertDatabaseHas('official_students', ['matric_number' => '250404002']);

        $import = DB::table('student_registry_imports')->latest('id')->first();
        $this->assertSame(1, $import->failed_rows);
        $summary = json_decode($import->error_summary, true);
        $this->assertSame('Missing required value: full_name', $summary['errors'][0]['reason']);
    }

    public function test_safe_normalization_handles_spaces_and_casing(): void
    {
        $csv = implode("\n", [
            'matric_number,full_name',
            '   250404001   ,Adeyemi Trimmed',
            '24 04 05 002,Okonkwo Spaced',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('spaces.csv', $csv),
            ]);

        $this->assertDatabaseHas('official_students', ['matric_number' => '250404001']);
        $this->assertDatabaseHas('official_students', ['matric_number' => '240405002']);
    }

    public function test_existing_student_portal_account_credentials_and_photos_remain_intact(): void
    {
        // 1. Existing official student
        DB::table('official_students')->insert([
            'matric_number' => '220404008',
            'full_name'     => 'Old Official Name',
            'department'    => 'Computer Science',
            'faculty'       => 'Faculty of Computing',
            'level'         => '300',
            'status'        => 'active',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $sessionId = DB::table('exam_sessions')->where('is_active', true)->value('session_id')
            ?? DB::table('exam_sessions')->insertGetId([
                'semester'      => 'First Semester',
                'academic_year' => '2025/2026',
                'is_active'     => true,
                'fee_amount'    => 100000,
                'aes_key'       => bin2hex(random_bytes(32)),
                'hmac_secret'   => bin2hex(random_bytes(32)),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

        // 2. Existing portal account in `students` with password and approved photo
        DB::table('students')->insert([
            'matric_no'       => '220404008',
            'full_name'       => 'Old Official Name',
            'department_id'   => 1,
            'session_id'      => $sessionId,
            'level'           => '300',
            'photo_path'      => 'passports/secret-photo.jpg',
            'photo_status'    => 'approved',
            'password'        => bcrypt('secretpassword123'),
            'account_status'  => 'active',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // 3. Re-import registry with matching name and updated level
        $csv = implode("\n", [
            'matric_number,full_name,level',
            '220404008,Old Official Name,400',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('portal_sync.csv', $csv),
            ]);

        // Verify `students` table has updated level, while name, password and photo are UNTOUCHED
        $portalAccount = DB::table('students')->where('matric_no', '220404008')->first();
        $this->assertSame('Old Official Name', $portalAccount->full_name);
        $this->assertSame('400', $portalAccount->level);
        $this->assertSame('passports/secret-photo.jpg', $portalAccount->photo_path);
        $this->assertSame('approved', $portalAccount->photo_status);
        $this->assertTrue(password_verify('secretpassword123', $portalAccount->password));

        // Still only 1 portal account
        $this->assertDatabaseCount('students', 1);
    }

    public function test_preview_endpoint_analyzes_file_without_committing_database_changes(): void
    {
        $csv = implode("\n", [
            'matric_number,full_name',
            '250404001,Valid New Student',
            '25040401,Invalid Length',
            '250404002,', // Missing name
        ]);

        $response = $this
            ->withSession($this->adminSession())
            ->postJson(route('admin.student-registry.preview'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('preview.csv', $csv),
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.total_rows', 3);
        $response->assertJsonPath('data.new_valid_count', 1);
        $response->assertJsonPath('data.invalid_count', 2);
        $response->assertJsonPath('data.missing_name_count', 1);

        // Crucial: Preview must NOT write records to official_students!
        $this->assertDatabaseCount('official_students', 0);
    }

    public function test_header_aliases_accepted_seamlessly(): void
    {
        $csv = implode("\n", [
            'matric_no,student_name,dept',
            '250404001,Adeyemi Oluwaseun,Computer Science',
        ]);

        $response = $this
            ->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('aliases.csv', $csv),
            ]);

        $response->assertRedirect(route('admin.student-registry'));
        $this->assertDatabaseCount('official_students', 1);
        $this->assertDatabaseHas('official_students', [
            'matric_number' => '250404001',
            'full_name'     => 'Adeyemi Oluwaseun',
            'department'    => 'Computer Science',
        ]);
    }

    public function test_large_batch_import_processes_efficiently_within_transaction(): void
    {
        $rows = ['matric_number,full_name'];
        for ($i = 1; $i <= 100; $i++) {
            $seq = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $rows[] = "250404{$seq},Student Batch {$i}";
        }
        $csv = implode("\n", $rows);

        $response = $this
            ->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('large_batch.csv', $csv),
            ]);

        $response->assertRedirect(route('admin.student-registry'));
        $this->assertDatabaseCount('official_students', 100);

        $import = DB::table('student_registry_imports')->latest('id')->first();
        $this->assertSame(100, $import->total_rows);
        $this->assertSame(100, $import->imported_rows);
        $this->assertSame(0, $import->failed_rows);
    }
}
