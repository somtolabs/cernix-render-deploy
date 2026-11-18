<?php

namespace Tests\Feature;

use App\Models\OfficialStudent;
use App\Services\MatricParseResult;
use App\Services\MatriculationNumberService;
use App\Services\QrTokenService;
use App\Services\RegistrationService;
use App\Services\StudentRegistryImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MatriculationAcademicMappingTest extends TestCase
{
    use RefreshDatabase;

    private MatriculationNumberService $matricService;
    private StudentRegistryImportService $importService;
    private QrTokenService $qrTokenService;
    private RegistrationService $registrationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matricService = app(MatriculationNumberService::class);
        $this->matricService->clearCache();
        $this->importService = app(StudentRegistryImportService::class);
        $this->qrTokenService = app(QrTokenService::class);
        $this->registrationService = app(RegistrationService::class);

        // Seed core verified departments
        DB::table('departments')->insertOrIgnore([
            ['dept_id' => 1, 'dept_name' => 'Computer Science',       'faculty' => 'Faculty of Computing', 'department_code' => '04', 'faculty_code' => '04'],
            ['dept_id' => 2, 'dept_name' => 'Software Engineering',   'faculty' => 'Faculty of Computing', 'department_code' => '05', 'faculty_code' => '04'],
            ['dept_id' => 3, 'dept_name' => 'Information Technology', 'faculty' => 'Faculty of Computing', 'department_code' => '06', 'faculty_code' => '04'],
            ['dept_id' => 4, 'dept_name' => 'Cyber Security',         'faculty' => 'Faculty of Computing', 'department_code' => '07', 'faculty_code' => '04'],
            ['dept_id' => 5, 'dept_name' => 'Data Science',           'faculty' => 'Faculty of Computing', 'department_code' => '08', 'faculty_code' => '04'],
        ]);
    }

    public function test_admission_year_is_extracted_accurately_and_never_treated_as_academic_level(): void
    {
        // 250404001 decodes to admission year 2025, NOT 100 level
        $parseResult = $this->matricService->parse('250404001', verifyRegistry: false);

        $this->assertSame(2025, $parseResult->admission_year);
        $this->assertSame('25', $parseResult->admission_year_code);
        $this->assertSame('04', $parseResult->faculty_code);
        $this->assertSame('04', $parseResult->department_code);
        $this->assertSame('001', $parseResult->student_sequence);

        // Components do not infer or supply academic level
        $components = $this->matricService->decodeComponents('250404001');
        $this->assertArrayNotHasKey('level', $components);
        $this->assertSame(2025, $components['admission_year']);
    }

    public function test_students_with_identical_matric_prefix_can_have_different_current_academic_levels(): void
    {
        // Student A: Admitted 2022, normal progression to 400L
        $studentA = OfficialStudent::create([
            'matric_number' => '220404001',
            'full_name'     => 'Ayomide Adeyemi Normal',
            'department'    => 'Computer Science',
            'faculty'       => 'Faculty of Computing',
            'level'         => '400',
            'status'        => 'active',
        ]);

        // Student B: Admitted 2022, carry-over student currently in 300L
        $studentB = OfficialStudent::create([
            'matric_number' => '220404002',
            'full_name'     => 'Chidera Eze Carryover',
            'department'    => 'Computer Science',
            'faculty'       => 'Faculty of Computing',
            'level'         => '300',
            'status'        => 'active',
        ]);

        $this->assertSame(2022, $studentA->admission_year);
        $this->assertSame(2022, $studentB->admission_year);
        $this->assertSame('400', $studentA->level);
        $this->assertSame('300', $studentB->level);

        $this->assertSame('220404001', $studentA->normalized_matric_number);
        $this->assertSame('220404002', $studentB->normalized_matric_number);
    }

    public function test_minimal_two_column_csv_derives_institutional_info_without_fabricating_level(): void
    {
        $csvContent = "matric_number,full_name\n" .
                      "250404001,Adeyemi Oluwaseun Miracle\n" .
                      "240405002,Okonkwo Chidinma Peace\n";

        $file = UploadedFile::fake()->createWithContent('minimal_registry.csv', $csvContent);

        $result = $this->importService->import($file, 'Test Officer');

        $this->assertSame(2, $result['imported_rows']);
        $this->assertSame(0, $result['failed_rows']);

        $student1 = OfficialStudent::where('matric_number', '250404001')->first();
        $this->assertNotNull($student1);
        $this->assertSame('Adeyemi Oluwaseun Miracle', $student1->full_name);
        $this->assertSame(2025, $student1->admission_year);
        $this->assertSame('04', $student1->faculty_code);
        $this->assertSame('04', $student1->department_code);
        $this->assertSame('Computer Science', $student1->department);
        $this->assertSame('Faculty of Computing', $student1->faculty);
        // Level is computed from admission year and active session start year (2025 -> 100)
        $this->assertSame('100', $student1->level);

        $student2 = OfficialStudent::where('matric_number', '240405002')->first();
        $this->assertNotNull($student2);
        $this->assertSame(2024, $student2->admission_year);
        $this->assertSame('Software Engineering', $student2->department);
        // Level is computed from admission year and active session start year (2024 -> 200)
        $this->assertSame('200', $student2->level);
    }

    public function test_explicit_level_in_csv_is_preserved_as_authoritative_level(): void
    {
        $csvContent = "matric_number,full_name,level\n" .
                      "220404005,Tunde Michael Bello,300\n";

        $file = UploadedFile::fake()->createWithContent('level_registry.csv', $csvContent);

        $result = $this->importService->import($file, 'Test Officer');
        $this->assertSame(1, $result['imported_rows']);

        $student = OfficialStudent::where('matric_number', '220404005')->first();
        $this->assertNotNull($student);
        // Admission year is 2022 from matriculation intelligence
        $this->assertSame(2022, $student->admission_year);
        // Current level is explicitly 300 from the authoritative import column
        $this->assertSame('300', $student->level);
    }

    public function test_unmapped_faculty_and_department_codes_remain_unmapped(): void
    {
        // Faculty 01, Department 01 is not in the seeded computing departments
        $parseResult = $this->matricService->parse('250101001', verifyRegistry: false);

        $this->assertTrue($parseResult->isValidStructure());
        $this->assertFalse($parseResult->is_institutionally_mapped);
        $this->assertNull($parseResult->faculty_name);
        $this->assertNull($parseResult->department_name);
        $this->assertSame(2025, $parseResult->admission_year);
        $this->assertSame('01', $parseResult->faculty_code);
        $this->assertSame('01', $parseResult->department_code);
    }

    public function test_carryover_student_is_eligible_for_different_level_assessment_on_confirmed_roster(): void
    {
        $sessionId = DB::table('exam_sessions')->insertGetId([
            'semester'      => 'First Semester',
            'academic_year' => '2025/2026',
            'fee_amount'    => 10000.00,
            'aes_key'       => bin2hex(random_bytes(32)),
            'hmac_secret'   => bin2hex(random_bytes(32)),
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Student is officially at 400 Level
        $matricNo = '220404010';
        DB::table('official_students')->insert([
            'matric_number'            => $matricNo,
            'normalized_matric_number' => $matricNo,
            'admission_year'           => 2022,
            'faculty_code'             => '04',
            'department_code'          => '04',
            'full_name'                => 'Amara Blessing Nwankwo',
            'department'               => 'Computer Science',
            'faculty'                  => 'Faculty of Computing',
            'level'                    => '400',
            'status'                   => 'active',
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        DB::table('students')->insert([
            'matric_no'     => $matricNo,
            'full_name'     => 'Amara Blessing Nwankwo',
            'department_id' => 1,
            'level'         => '400',
            'session_id'    => $sessionId,
            'photo_path'    => 'demo-passports/student-010.jpg',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Timetable is a 200 Level course (CSC201)
        $timetableId = DB::table('timetables')->insertGetId([
            'exam_session_id'     => $sessionId,
            'department_id'       => 1,
            'level'               => '200',
            'course_code'         => 'CSC201',
            'course_title'        => 'Computer Programming II',
            'exam_date'           => Carbon::today()->toDateString(),
            'start_time'          => '09:00',
            'end_time'            => '12:00',
            'venue'               => 'CBT Hall B',
            'status'              => 'scheduled',
            'register_status'     => 'confirmed',
            'roster_confirmed_at' => now(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // Student is enrolled on the confirmed roster as carry-over candidate
        DB::table('timetable_students')->insert([
            'timetable_id'   => $timetableId,
            'matric_no'      => $matricNo,
            'candidate_type' => 'carry_over',
            'created_at'     => now(),
        ]);

        // 1. Issue QR token: must succeed despite student level (400) != timetable level (200)
        $issued = $this->qrTokenService->issue($matricNo, $sessionId, $timetableId);
        $this->assertNotEmpty($issued['token_id']);
        $this->assertNotEmpty($issued['qr_svg']);
        $this->assertNotEmpty($issued['qr_content']);

        // 2. Verify QR token: must succeed and approve candidate
        $examinerId = DB::table('examiners')->insertGetId([
            'username'      => 'invigilator1',
            'full_name'     => 'Dr. Examination Officer',
            'role'          => 'examiner',
            'password_hash' => bcrypt('password123'),
            'is_active'     => true,
            'created_at'    => now(),
        ]);

        $verified = $this->qrTokenService->verify(
            $issued['qr_content'],
            $examinerId,
            'device_fingerprint_test',
            '127.0.0.1'
        );

        $this->assertSame('APPROVED', $verified['decision']);
        $this->assertSame($matricNo, $verified['student']['matric_no']);
    }

    public function test_registration_service_resolves_official_student_and_department(): void
    {
        $sessionId = DB::table('exam_sessions')->insertGetId([
            'semester'      => 'First Semester',
            'academic_year' => '2025/2026',
            'fee_amount'    => 10000.00,
            'aes_key'       => bin2hex(random_bytes(32)),
            'hmac_secret'   => bin2hex(random_bytes(32)),
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $matricNo = '250406001'; // 2025, Computing (04), Information Technology (06)
        DB::table('official_students')->insert([
            'matric_number'            => $matricNo,
            'normalized_matric_number' => $matricNo,
            'admission_year'           => 2025,
            'faculty_code'             => '04',
            'department_code'          => '06',
            'full_name'                => 'Toluwani Deborah Akinola',
            'department'               => 'Information Technology',
            'faculty'                  => 'Faculty of Computing',
            'level'                    => '100',
            'status'                   => 'active',
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        $result = $this->registrationService->registerStudent([
            'matric_no'  => '  250406001  ', // Raw matric with spaces
            'session_id' => $sessionId,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame($matricNo, $result['data']['matric_no']);

        $student = DB::table('students')->where('matric_no', $matricNo)->first();
        $this->assertNotNull($student);
        $this->assertSame('Toluwani Deborah Akinola', $student->full_name);
        $this->assertSame(3, (int) $student->department_id); // Information Technology dept_id
        $this->assertSame('100', $student->level);
    }
}
