<?php

namespace Tests\Feature;

use App\Models\OfficialStudent;
use App\Models\StudentRegistryImport;
use App\Services\AuditService;
use App\Services\MatriculationNumberService;
use App\Services\StudentRegistryImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentRegistryDuplicateDetectionAndIntegrityTest extends TestCase
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
            'examiner_id'       => 'admin-super',
            'examiner_username' => 'superadmin',
            'examiner_role'     => 'super_admin',
        ];
    }

    private function activeSessionId(): int
    {
        return DB::table('exam_sessions')->where('is_active', true)->value('session_id')
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
    }

    public function test_identical_student_repeated_within_one_document(): void
    {
        $csv = implode("\n", [
            'matric_number,full_name',
            '250404001,Adeyemi Oluwaseun',
            '250404001,Adeyemi Oluwaseun', // identical repetition
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('dupes.csv', $csv),
            ]);

        $this->assertDatabaseCount('official_students', 1);
        $this->assertDatabaseHas('official_students', [
            'matric_number' => '250404001',
            'full_name'     => 'Adeyemi Oluwaseun',
        ]);

        $import = DB::table('student_registry_imports')->latest('id')->first();
        $this->assertSame(1, $import->imported_rows);
        $this->assertSame(1, $import->failed_rows);
    }

    public function test_conflicting_names_within_one_document_flags_conflict(): void
    {
        $csv = implode("\n", [
            'matric_number,full_name',
            '250404001,John Ade',
            '250404001,James Bello', // conflicting name
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('conflict_names.csv', $csv),
            ]);

        // First occurrence is saved, second conflicting occurrence does not overwrite
        $this->assertDatabaseCount('official_students', 1);
        $this->assertDatabaseHas('official_students', [
            'matric_number' => '250404001',
            'full_name'     => 'John Ade',
        ]);

        $import = DB::table('student_registry_imports')->latest('id')->first();
        $summary = json_decode($import->error_summary, true);
        $this->assertSame(1, $summary['conflict_count']);
        $this->assertSame('duplicate_in_file_conflict', $summary['conflicts'][0]['type']);
    }

    public function test_identical_student_appearing_in_two_uploaded_documents(): void
    {
        $csv1 = implode("\n", [
            'matric_number,full_name',
            '250404001,John Ade',
        ]);

        $csv2 = implode("\n", [
            'matric_number,full_name',
            '250404001,John Ade',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_files' => [
                    UploadedFile::fake()->createWithContent('doc_a.csv', $csv1),
                    UploadedFile::fake()->createWithContent('doc_b.csv', $csv2),
                ],
            ]);

        // Exactly one student identity, not two
        $this->assertDatabaseCount('official_students', 1);
        $this->assertDatabaseHas('official_students', [
            'matric_number' => '250404001',
            'full_name'     => 'John Ade',
        ]);

        $import = DB::table('student_registry_imports')->latest('id')->first();
        $this->assertSame(1, $import->imported_rows);
        $this->assertSame(1, $import->skipped_rows);
    }

    public function test_conflicting_identities_across_two_uploaded_documents(): void
    {
        $csv1 = implode("\n", [
            'matric_number,full_name',
            '250404001,John Ade',
        ]);

        $csv2 = implode("\n", [
            'matric_number,full_name',
            '250404001,James Bello',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_files' => [
                    UploadedFile::fake()->createWithContent('doc_a.csv', $csv1),
                    UploadedFile::fake()->createWithContent('doc_b.csv', $csv2),
                ],
            ]);

        // First doc accepted; second conflicting entry does not overwrite first
        $this->assertDatabaseCount('official_students', 1);
        $this->assertDatabaseHas('official_students', [
            'matric_number' => '250404001',
            'full_name'     => 'John Ade',
        ]);

        $import = DB::table('student_registry_imports')->latest('id')->first();
        $summary = json_decode($import->error_summary, true);
        $this->assertSame(1, $summary['conflict_count']);
        $this->assertSame('duplicate_across_files_conflict', $summary['conflicts'][0]['type']);
    }

    public function test_re_uploading_existing_document_does_not_inflate_student_count(): void
    {
        $csv = implode("\n", [
            'matric_number,full_name',
            '250404001,John Ade',
            '240405002,Chidinma Peace',
        ]);

        // First upload
        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('batch1.csv', $csv),
            ]);
        $this->assertDatabaseCount('official_students', 2);

        // Re-upload same document
        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('batch1.csv', $csv),
            ]);

        // Count remains exactly 2
        $this->assertDatabaseCount('official_students', 2);
    }

    public function test_existing_student_receiving_safe_additional_information(): void
    {
        DB::table('official_students')->insert([
            'matric_number'            => '250404001',
            'normalized_matric_number' => '250404001',
            'full_name'                => 'John Ade',
            'department'               => null,
            'faculty'                  => null,
            'level'                    => null,
            'status'                   => 'active',
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        $csv = implode("\n", [
            'matric_number,full_name,level',
            '250404001,John Ade,300',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('safe_update.csv', $csv),
            ]);

        $this->assertDatabaseCount('official_students', 1);
        $this->assertDatabaseHas('official_students', [
            'matric_number' => '250404001',
            'full_name'     => 'John Ade',
            'level'         => '300',
            'department'    => 'Computer Science', // auto-derived safely
        ]);
    }

    public function test_conflicting_optional_fields_flags_inconsistency(): void
    {
        DB::table('official_students')->insert([
            'matric_number'            => '250404001',
            'normalized_matric_number' => '250404001',
            'full_name'                => 'John Ade',
            'department'               => 'Computer Science',
            'faculty'                  => 'Faculty of Computing',
            'level'                    => '100',
            'status'                   => 'active',
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        $csv = implode("\n", [
            'matric_number,full_name,department',
            '250404001,John Ade,Biochemistry',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('dept_conflict.csv', $csv),
            ]);

        $import = DB::table('student_registry_imports')->latest('id')->first();
        $summary = json_decode($import->error_summary, true);

        $this->assertNotEmpty($summary['conflicts']);
        $types = collect($summary['conflicts'])->pluck('type')->all();
        $this->assertContains('supplied_department_inconsistency', $types);
    }

    public function test_identity_conflict_different_name_does_not_overwrite_db_record(): void
    {
        DB::table('official_students')->insert([
            'matric_number'            => '250404001',
            'normalized_matric_number' => '250404001',
            'full_name'                => 'Official Authoritative Name',
            'department'               => 'Computer Science',
            'faculty'                  => 'Faculty of Computing',
            'level'                    => '100',
            'status'                   => 'active',
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        $csv = implode("\n", [
            'matric_number,full_name',
            '250404001,Completely Different Person',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('identity_conflict.csv', $csv),
            ]);

        // CRUCIAL: Database record was NOT overwritten!
        $this->assertDatabaseHas('official_students', [
            'matric_number' => '250404001',
            'full_name'     => 'Official Authoritative Name',
        ]);
        $this->assertDatabaseMissing('official_students', [
            'full_name' => 'Completely Different Person',
        ]);

        $import = DB::table('student_registry_imports')->latest('id')->first();
        $summary = json_decode($import->error_summary, true);
        $this->assertSame(1, $summary['conflict_count']);
        $this->assertSame('identity_conflict', $summary['conflicts'][0]['type']);
    }

    public function test_administrator_approved_conflict_resolution_with_audit_trail(): void
    {
        DB::table('official_students')->insert([
            'matric_number'            => '250404001',
            'normalized_matric_number' => '250404001',
            'full_name'                => 'Old Name',
            'department'               => 'Computer Science',
            'faculty'                  => 'Faculty of Computing',
            'level'                    => '100',
            'status'                   => 'active',
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        // Admin approves applying incoming correction
        $response = $this->withSession($this->adminSession())
            ->postJson(route('admin.student-registry.resolve-conflict'), [
                'matric_number'   => '250404001',
                'action'          => 'apply_incoming',
                'incoming_record' => [
                    'full_name'  => 'Approved Corrected Name',
                    'department' => 'Computer Science',
                    'level'      => '200',
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Record is updated in place
        $this->assertDatabaseCount('official_students', 1);
        $this->assertDatabaseHas('official_students', [
            'matric_number' => '250404001',
            'full_name'     => 'Approved Corrected Name',
            'level'         => '200',
        ]);

        // Audit log verified
        $audit = DB::table('audit_log')->latest('id')->first();
        $this->assertSame('student_registry.conflict_resolved', $audit->action);
        $this->assertSame('250404001', $audit->target_id);

        $before = json_decode($audit->before_values, true);
        $after  = json_decode($audit->after_values, true);
        $this->assertSame('Old Name', $before['full_name']);
        $this->assertSame('Approved Corrected Name', $after['full_name']);
    }

    public function test_conflict_resolution_manual_correct_and_keep_existing(): void
    {
        DB::table('official_students')->insert([
            'matric_number'            => '240402001',
            'normalized_matric_number' => '240402001',
            'full_name'                => 'Student A',
            'department'               => 'Biochemistry',
            'status'                   => 'active',
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        // Test manual_correct
        $this->withSession($this->adminSession())
            ->postJson(route('admin.student-registry.resolve-conflict'), [
                'matric_number' => '240402001',
                'action'        => 'manual_correct',
                'corrections'   => [
                    'full_name' => 'Manually Verified Name',
                    'level'     => '300',
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('official_students', [
            'matric_number' => '240402001',
            'full_name'     => 'Manually Verified Name',
            'level'         => '300',
        ]);

        // Test keep_existing
        $this->withSession($this->adminSession())
            ->postJson(route('admin.student-registry.resolve-conflict'), [
                'matric_number' => '240402001',
                'action'        => 'keep_existing',
            ])
            ->assertOk();

        // Still retains manually verified values
        $this->assertDatabaseHas('official_students', [
            'matric_number' => '240402001',
            'full_name'     => 'Manually Verified Name',
        ]);
    }

    public function test_normalization_related_duplicate_detection_and_db_uniqueness(): void
    {
        $csv = implode("\n", [
            'matric_number,full_name',
            '  250404001  ,Space Student 1',
            '25 04 04 001,Space Student 2',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('spaces.csv', $csv),
            ]);

        // Normalized matric is identical: exactly 1 student saved
        $this->assertDatabaseCount('official_students', 1);
        $this->assertDatabaseHas('official_students', [
            'normalized_matric_number' => '250404001',
        ]);
    }

    public function test_inactive_student_status_preserved_when_omitted(): void
    {
        DB::table('official_students')->insert([
            'matric_number'            => '250404001',
            'normalized_matric_number' => '250404001',
            'full_name'                => 'Inactive Student',
            'department'               => 'Computer Science',
            'faculty'                  => 'Faculty of Computing',
            'level'                    => '200',
            'status'                   => 'inactive', // inactive
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        // Import 2-column CSV without status
        $csv = implode("\n", [
            'matric_number,full_name',
            '250404001,Inactive Student',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('inactive.csv', $csv),
            ]);

        // Must NOT reactivate student! Status remains 'inactive'
        $student = DB::table('official_students')->where('matric_number', '250404001')->first();
        $this->assertSame('inactive', $student->status);
    }

    public function test_preview_and_commit_consistency_for_all_classifications(): void
    {
        DB::table('official_students')->insert([
            'matric_number'            => '250404001',
            'normalized_matric_number' => '250404001',
            'full_name'                => 'Existing Matches',
            'department'               => 'Computer Science',
            'faculty'                  => 'Faculty of Computing',
            'level'                    => '100',
            'status'                   => 'active',
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        DB::table('official_students')->insert([
            'matric_number'            => '240402001',
            'normalized_matric_number' => '240402001',
            'full_name'                => 'Existing Different Name',
            'department'               => 'Biochemistry',
            'status'                   => 'active',
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        $csv = implode("\n", [
            'matric_number,full_name,level',
            '250404001,Existing Matches,100',         // B: Existing no changes
            '240402001,Incoming Different Name,100', // F: Identity conflict
            '250404002,New Valid Student,100',        // A: New student
            '250404002,New Valid Student,100',        // D: Duplicate in file
        ]);

        $file = UploadedFile::fake()->createWithContent('preview_test.csv', $csv);

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.preview'), [
                'registry_csv' => $file,
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.new_student_count', 1);
        $response->assertJsonPath('data.existing_no_changes_count', 1);
        $response->assertJsonPath('data.identity_conflict_count', 1);
        $response->assertJsonPath('data.duplicate_in_file_count', 1);
    }

    public function test_duplicate_portal_accounts_detected_without_automatic_merging(): void
    {
        $sessionId = $this->activeSessionId();

        // 1. Existing portal account with password and verified photo
        DB::table('students')->insert([
            'matric_no'       => '220404008',
            'full_name'       => 'Portal Student',
            'department_id'   => 1,
            'session_id'      => $sessionId,
            'level'           => '300',
            'photo_path'      => 'passports/secret-photo.jpg',
            'photo_status'    => 'approved',
            'password'        => bcrypt('original_password_hash'),
            'account_status'  => 'active',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // 2. Import registry with same student
        $csv = implode("\n", [
            'matric_number,full_name,level',
            '220404008,Portal Student,400',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('portal_preserve.csv', $csv),
            ]);

        // Official student created/updated
        $this->assertDatabaseHas('official_students', [
            'matric_number' => '220404008',
            'level'         => '400',
        ]);

        // Portal account has updated level, but password and photo are UNTOUCHED
        $portal = DB::table('students')->where('matric_no', '220404008')->first();
        $this->assertSame('passports/secret-photo.jpg', $portal->photo_path);
        $this->assertSame('approved', $portal->photo_status);
        $this->assertTrue(password_verify('original_password_hash', $portal->password));

        // Still exactly one portal account (no duplicates created)
        $this->assertDatabaseCount('students', 1);
    }

    public function test_legacy_full_column_csv_backward_compatibility(): void
    {
        $csv = implode("\n", [
            'matric_number,full_name,department,faculty,level,programme,academic_session,status',
            '250404001,Full Legacy Student,Computer Science,Faculty of Computing,100,B.Sc Computer Science,2025/2026,active',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('legacy_full.csv', $csv),
            ]);

        $this->assertDatabaseHas('official_students', [
            'matric_number'    => '250404001',
            'full_name'        => 'Full Legacy Student',
            'department'       => 'Computer Science',
            'faculty'          => 'Faculty of Computing',
            'level'            => '100',
            'programme'        => 'B.Sc Computer Science',
            'academic_session' => '2025/2026',
            'status'           => 'active',
        ]);
    }
}
