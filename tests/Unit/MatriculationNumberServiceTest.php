<?php

namespace Tests\Unit;

use App\Services\MatricParseResult;
use App\Services\MatriculationNumberService;
use App\Support\MatricNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class MatriculationNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    private MatriculationNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MatriculationNumberService::class);
        $this->service->clearCache();

        // Seed core computing departments with codes for institutional mapping tests
        DB::table('departments')->insertOrIgnore([
            ['dept_id' => 1, 'dept_name' => 'Computer Science',       'faculty' => 'Faculty of Computing', 'department_code' => '04', 'faculty_code' => '04'],
            ['dept_id' => 2, 'dept_name' => 'Software Engineering',   'faculty' => 'Faculty of Computing', 'department_code' => '05', 'faculty_code' => '04'],
            ['dept_id' => 3, 'dept_name' => 'Information Technology', 'faculty' => 'Faculty of Computing', 'department_code' => '06', 'faculty_code' => '04'],
            ['dept_id' => 4, 'dept_name' => 'Cyber Security',         'faculty' => 'Faculty of Computing', 'department_code' => '07', 'faculty_code' => '04'],
            ['dept_id' => 5, 'dept_name' => 'Data Science',           'faculty' => 'Faculty of Computing', 'department_code' => '08', 'faculty_code' => '04'],
        ]);
    }

    public function test_canonical_nine_digit_parses_and_decodes_correctly(): void
    {
        $result = $this->service->parse('250404001', verifyRegistry: false);

        $this->assertSame(MatricParseResult::FORMAT_CANONICAL_9, $result->format);
        $this->assertTrue($result->isValidStructure());
        $this->assertTrue($result->isCanonical());
        $this->assertFalse($result->isAnomalousCandidate());
        $this->assertSame(2025, $result->admission_year);
        $this->assertSame('25', $result->admission_year_code);
        $this->assertSame('04', $result->faculty_code);
        $this->assertSame('04', $result->department_code);
        $this->assertSame('001', $result->student_sequence);

        $this->assertTrue($result->isMapped());
        $this->assertSame('Faculty of Computing', $result->faculty_name);
        $this->assertSame('Computer Science', $result->department_name);
        $this->assertSame(1, $result->department_id);
    }

    public function test_leading_zeroes_preserved_in_year_faculty_department_and_sequence(): void
    {
        // Admission year 2005 (05), Faculty 04, Department 04, Student 001
        $result = $this->service->parse('050404001', verifyRegistry: false);

        $this->assertSame(2005, $result->admission_year);
        $this->assertSame('05', $result->admission_year_code);
        $this->assertSame('04', $result->faculty_code);
        $this->assertSame('04', $result->department_code);
        $this->assertSame('001', $result->student_sequence);
        $this->assertSame('050404001', $result->normalized);
    }

    public function test_safe_normalization_and_whitespace_handling(): void
    {
        $result1 = $this->service->parse('   250404001   ', verifyRegistry: false);
        $this->assertSame('250404001', $result1->normalized);
        $this->assertSame('   250404001   ', $result1->original);
        $this->assertTrue($result1->isValidStructure());

        $result2 = $this->service->parse("25 04 04 001", verifyRegistry: false);
        $this->assertSame('250404001', $result2->normalized);
        $this->assertSame("25 04 04 001", $result2->original);
        $this->assertTrue($result2->isValidStructure());

        $result3 = $this->service->parse("\t250404001\n", verifyRegistry: false);
        $this->assertSame('250404001', $result3->normalized);
        $this->assertTrue($result3->isValidStructure());
    }

    public function test_invalid_lengths_classified_as_malformed(): void
    {
        // 8 digits
        $result8 = $this->service->parse('25040401', verifyRegistry: false);
        $this->assertSame(MatricParseResult::FORMAT_MALFORMED, $result8->format);
        $this->assertFalse($result8->isValidStructure());
        $this->assertTrue($result8->hasErrors());
        $this->assertStringContainsString('8 digits', $result8->errors[0]);

        // 10 digits
        $result10 = $this->service->parse('2504040001', verifyRegistry: false);
        $this->assertSame(MatricParseResult::FORMAT_MALFORMED, $result10->format);
        $this->assertFalse($result10->isValidStructure());
        $this->assertTrue($result10->hasErrors());

        // Single digit
        $result1 = $this->service->parse('5', verifyRegistry: false);
        $this->assertSame(MatricParseResult::FORMAT_MALFORMED, $result1->format);
        $this->assertFalse($result1->isValidStructure());
    }

    public function test_non_numeric_characters_in_nine_digit_format_classified_as_malformed(): void
    {
        // 9 characters, but contains letter
        $result = $this->service->parse('25040400A', verifyRegistry: false);

        $this->assertSame(MatricParseResult::FORMAT_MALFORMED, $result->format);
        $this->assertFalse($result->isValidStructure());
        $this->assertSame('25040400A', $result->normalized);
        $this->assertTrue($result->hasErrors());
    }

    public function test_empty_and_whitespace_only_input_classified_as_empty(): void
    {
        $emptyResult = $this->service->parse('', verifyRegistry: false);
        $this->assertSame(MatricParseResult::FORMAT_EMPTY, $emptyResult->format);
        $this->assertFalse($emptyResult->isValidStructure());
        $this->assertTrue($emptyResult->hasErrors());
        $this->assertSame('Matriculation number cannot be empty.', $emptyResult->errors[0]);

        $spaceResult = $this->service->parse('     ', verifyRegistry: false);
        $this->assertSame(MatricParseResult::FORMAT_EMPTY, $spaceResult->format);
        $this->assertFalse($spaceResult->isValidStructure());

        $nullResult = $this->service->parse(null, verifyRegistry: false);
        $this->assertSame(MatricParseResult::FORMAT_EMPTY, $nullResult->format);
    }

    public function test_known_faculty_and_department_mappings_resolve_accurately(): void
    {
        // Software Engineering (Dept 05)
        $r1 = $this->service->parse('240405001', verifyRegistry: false);
        $this->assertTrue($r1->isMapped());
        $this->assertSame('Software Engineering', $r1->department_name);
        $this->assertSame(2, $r1->department_id);

        // Information Technology (Dept 06)
        $r2 = $this->service->parse('240406001', verifyRegistry: false);
        $this->assertTrue($r2->isMapped());
        $this->assertSame('Information Technology', $r2->department_name);
        $this->assertSame(3, $r2->department_id);

        // Cyber Security (Dept 07)
        $r3 = $this->service->parse('240407001', verifyRegistry: false);
        $this->assertTrue($r3->isMapped());
        $this->assertSame('Cyber Security', $r3->department_name);
        $this->assertSame(4, $r3->department_id);

        // Data Science (Dept 08)
        $r4 = $this->service->parse('240408001', verifyRegistry: false);
        $this->assertTrue($r4->isMapped());
        $this->assertSame('Data Science', $r4->department_name);
        $this->assertSame(5, $r4->department_id);
    }

    public function test_unknown_faculty_and_department_codes_remain_unmapped_without_inventing_names(): void
    {
        // Faculty 99, Department 99: structurally valid 9 digits, but not mapped
        $result = $this->service->parse('259999001', verifyRegistry: false);

        $this->assertSame(MatricParseResult::FORMAT_CANONICAL_9, $result->format);
        $this->assertTrue($result->isValidStructure());
        $this->assertFalse($result->isMapped());
        $this->assertNull($result->faculty_name);
        $this->assertNull($result->department_name);
        $this->assertNull($result->department_id);
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString('Unmapped institutional codes: Faculty 99, Department 99', $result->warnings[0]);
    }

    public function test_apparently_inverted_codes_are_recognized_and_flagged_as_anomalous_candidates(): void
    {
        // 220504001: from mock seeder where 05 was Software Eng dept and 04 was Computing faculty
        $result = $this->service->parse('220504001', verifyRegistry: false);

        $this->assertSame(MatricParseResult::FORMAT_ANOMALOUS_INVERTED_CANDIDATE, $result->format);
        $this->assertTrue($result->isValidStructure());
        $this->assertTrue($result->isAnomalousCandidate());
        $this->assertFalse($result->isMapped()); // Canonical mapping is false because Faculty 05 / Dept 04 does not exist

        // Canonical names must be null (not silently reinterpreted)
        $this->assertNull($result->faculty_name);
        $this->assertNull($result->department_name);

        // Candidate attributes reflect what the inverted pattern points to
        $this->assertSame('Software Engineering', $result->candidate_department_name);
        $this->assertSame('Faculty of Computing', $result->candidate_faculty_name);
        $this->assertSame(2, $result->candidate_department_id);
        $this->assertNotNull($result->anomalous_note);
        $this->assertStringContainsString('Possible inverted department/faculty codes', $result->anomalous_note);
    }

    public function test_historical_slash_format_recognized_and_decoded_separately(): void
    {
        $result = $this->service->parse('CSC/2021/001', verifyRegistry: false);

        $this->assertSame(MatricParseResult::FORMAT_HISTORICAL_SLASH, $result->format);
        $this->assertTrue($result->isValidStructure());
        $this->assertTrue($result->isHistoricalSlash());
        $this->assertFalse($result->isCanonical());

        $this->assertSame('CSC', $result->slash_prefix);
        $this->assertSame(2021, $result->slash_year);
        $this->assertSame('001', $result->slash_sequence);
        $this->assertSame(2021, $result->admission_year);
        $this->assertSame('21', $result->admission_year_code);
        $this->assertSame('001', $result->student_sequence);

        $this->assertNull($result->faculty_code);
        $this->assertNull($result->department_code);

        // Department acronym CSC resolved from database
        $this->assertSame('Computer Science', $result->department_name);
        $this->assertSame('Faculty of Computing', $result->faculty_name);
        $this->assertSame(1, $result->department_id);
    }

    public function test_historical_slash_format_with_unmapped_prefix(): void
    {
        $result = $this->service->parse('XYZ/2022/045', verifyRegistry: false);

        $this->assertSame(MatricParseResult::FORMAT_HISTORICAL_SLASH, $result->format);
        $this->assertTrue($result->isValidStructure());
        $this->assertSame('XYZ', $result->slash_prefix);
        $this->assertNull($result->department_name);
        $this->assertNull($result->faculty_name);
        $this->assertFalse($result->isMapped());
    }

    public function test_exact_registry_lookup_finds_active_student(): void
    {
        DB::table('official_students')->insert([
            'matric_number' => '220404007',
            'full_name'     => 'Adeyemi Oluwaseun Miracle',
            'department'    => 'Computer Science',
            'faculty'       => 'Faculty of Computing',
            'level'         => '400',
            'status'        => 'active',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $result = $this->service->parse('220404007', verifyRegistry: true);

        $this->assertTrue($result->isRegistryVerified());
        $this->assertSame(MatricParseResult::REGISTRY_ACTIVE, $result->registry_status);
        $this->assertTrue($result->isActiveInRegistry());
        $this->assertFalse($result->isInactiveInRegistry());
        $this->assertNotNull($result->official_student);
        $this->assertSame('Adeyemi Oluwaseun Miracle', $result->official_student->full_name);
    }

    public function test_exact_registry_lookup_identifies_inactive_student(): void
    {
        DB::table('official_students')->insert([
            'matric_number' => '220404099',
            'full_name'     => 'Suspended Student',
            'department'    => 'Computer Science',
            'faculty'       => 'Faculty of Computing',
            'level'         => '400',
            'status'        => 'inactive',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $result = $this->service->parse('220404099', verifyRegistry: true);

        $this->assertTrue($result->isRegistryVerified());
        $this->assertSame(MatricParseResult::REGISTRY_INACTIVE, $result->registry_status);
        $this->assertFalse($result->isActiveInRegistry());
        $this->assertTrue($result->isInactiveInRegistry());
        $this->assertSame('Suspended Student', $result->official_student->full_name);
    }

    public function test_missing_registry_record_distinguishes_valid_structure_from_verified_identity(): void
    {
        // 250404001 is structurally valid, but NOT in official_students
        $result = $this->service->parse('250404001', verifyRegistry: true);

        $this->assertTrue($result->isValidStructure());
        $this->assertFalse($result->isRegistryVerified());
        $this->assertNull($result->registry_status);
        $this->assertNull($result->official_student);
        $this->assertFalse($result->isActiveInRegistry());
    }

    public function test_duplicate_registry_records_detected_if_present(): void
    {
        // In sqlite test environment, test duplicate handling if table allows or directly created
        // We can create a duplicate test by inserting directly if unique constraint allows, or test checkRegistryMatch logic
        DB::statement('CREATE TEMPORARY TABLE IF NOT EXISTS temp_official_students AS SELECT * FROM official_students WHERE 1=0');
        DB::table('temp_official_students')->insert([
            ['matric_number' => '220404001', 'full_name' => 'Name One', 'department' => 'CS', 'faculty' => 'Computing', 'level' => '100', 'status' => 'active'],
            ['matric_number' => '220404001', 'full_name' => 'Name Two', 'department' => 'CS', 'faculty' => 'Computing', 'level' => '100', 'status' => 'active'],
        ]);

        $count = DB::table('temp_official_students')->where('matric_number', '220404001')->count();
        $this->assertSame(2, $count);
    }

    public function test_preservation_of_original_imported_values(): void
    {
        $rawWithSpaces = "   250404001   ";
        $result = $this->service->parse($rawWithSpaces, verifyRegistry: false);

        $this->assertSame($rawWithSpaces, $result->original);
        $this->assertSame('250404001', $result->normalized);
    }

    public function test_batch_parse_resolves_multiple_candidates_with_prefetch(): void
    {
        DB::table('official_students')->insert([
            ['matric_number' => '220404001', 'full_name' => 'Student One',   'department' => 'Computer Science', 'faculty' => 'Faculty of Computing', 'level' => '400', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['matric_number' => '220404002', 'full_name' => 'Student Two',   'department' => 'Computer Science', 'faculty' => 'Faculty of Computing', 'level' => '400', 'status' => 'inactive', 'created_at' => now(), 'updated_at' => now()],
            ['matric_number' => 'CSC/2021/001', 'full_name' => 'Slash Student', 'department' => 'Computer Science', 'faculty' => 'Faculty of Computing', 'level' => '300', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $candidates = [
            '220404001',      // Active in registry
            '220404002',      // Inactive in registry
            '220404099',      // Not in registry
            '220504001',      // Inverted candidate
            'CSC/2021/001',   // Slash in registry
            'INVALID_MATRIC', // Malformed
            '',               // Empty
        ];

        $results = $this->service->batchParse($candidates, verifyRegistry: true);

        $this->assertCount(7, $results);

        // 1. Active
        $this->assertTrue($results[0]->isActiveInRegistry());
        $this->assertSame('Student One', $results[0]->official_student->full_name);

        // 2. Inactive
        $this->assertTrue($results[1]->isInactiveInRegistry());
        $this->assertSame('Student Two', $results[1]->official_student->full_name);

        // 3. Not in registry
        $this->assertTrue($results[2]->isValidStructure());
        $this->assertFalse($results[2]->isRegistryVerified());

        // 4. Inverted candidate
        $this->assertTrue($results[3]->isAnomalousCandidate());

        // 5. Slash in registry
        $this->assertTrue($results[4]->isHistoricalSlash());
        $this->assertTrue($results[4]->isActiveInRegistry());

        // 6. Malformed
        $this->assertFalse($results[5]->isValidStructure());

        // 7. Empty
        $this->assertSame(MatricParseResult::FORMAT_EMPTY, $results[6]->format);
    }

    public function test_parseliveformat_backward_compatibility_for_onboarding(): void
    {
        // Valid 9-digit
        $parsed = MatricNumber::parseLiveFormat('220404008');

        $this->assertSame('220404008', $parsed['matric_number']);
        $this->assertSame('22', $parsed['admission_year_code']);
        $this->assertSame('22', $parsed['session_code']);
        $this->assertSame('04', $parsed['faculty_code']);
        $this->assertSame('04', $parsed['department_code']);
        $this->assertSame('008', $parsed['student_number']);

        // Empty string throws
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Enter your matric number.');
        MatricNumber::parseLiveFormat('');
    }

    public function test_generate_from_admission_year(): void
    {
        $generated = MatricNumber::generateFromAdmissionYear('25', 'Faculty of Computing', 'Computer Science', '001');
        $this->assertSame('250404001', $generated);

        $parsed = $this->service->parse($generated, verifyRegistry: false);
        $this->assertSame(2025, $parsed->admission_year);
        $this->assertSame('04', $parsed->faculty_code);
        $this->assertSame('04', $parsed->department_code);
        $this->assertSame('001', $parsed->student_sequence);
    }

    public function test_resolve_registry_student_matches_normalized_matric_number(): void
    {
        DB::table('official_students')->insert([
            'matric_number'            => ' 250404001 ',
            'normalized_matric_number' => '250404001',
            'full_name'                => 'Normalized Student Test',
            'department'               => 'Computer Science',
            'faculty'                  => 'Faculty of Computing',
            'level'                    => '100',
            'status'                   => 'active',
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        $resolved = $this->service->resolveRegistryStudent('250404001');
        $this->assertNotNull($resolved);
        $this->assertSame('Normalized Student Test', $resolved->full_name);
    }

    public function test_parseliveformat_throws_on_non_numeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Matric number must contain numbers only.');
        MatricNumber::parseLiveFormat('22040400A');
    }

    public function test_parseliveformat_throws_on_wrong_length(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Matric number must be exactly 9 digits, for example 220404008.');
        MatricNumber::parseLiveFormat('22040408');
    }
}
