<?php

namespace Tests\Feature;

use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class RegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private int $sessionId;
    private int $deptId;
    private string $matricNo = 'CSC/2021/001';

    protected function setUp(): void
    {
        parent::setUp();

        $this->deptId = DB::table('departments')->insertGetId([
            'dept_name' => 'Computer Science',
            'faculty' => 'Faculty of Computing',
        ]);
        $this->sessionId = DB::table('exam_sessions')->insertGetId([
            'semester' => 'First Semester',
            'academic_year' => '2025/2026',
            'fee_amount' => 10000,
            'aes_key' => bin2hex(random_bytes(32)),
            'hmac_secret' => bin2hex(random_bytes(32)),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('official_students')->insert([
            'matric_number' => $this->matricNo,
            'full_name' => 'Adebayo Oluwaseun Emmanuel',
            'department' => 'Computer Science',
            'faculty' => 'Faculty of Computing',
            'level' => '400',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_registration_creates_only_the_student_profile(): void
    {
        $result = $this->service()->registerStudent($this->validInput());

        $this->assertTrue($result['success']);
        $this->assertSame('Registration successful', $result['message']);
        $this->assertSame($this->matricNo, $result['data']['matric_no']);
        $this->assertSame('Adebayo Oluwaseun Emmanuel', $result['data']['full_name']);
        $this->assertDatabaseHas('students', [
            'matric_no' => $this->matricNo,
            'department_id' => $this->deptId,
            'session_id' => $this->sessionId,
        ]);
        $this->assertDatabaseCount('payment_records', 0);
        $this->assertDatabaseCount('qr_tokens', 0);
        $this->assertArrayNotHasKey('token_id', $result['data']);
    }

    public function test_identity_values_come_from_sis(): void
    {
        $result = $this->service()->registerStudent($this->validInput());

        $this->assertSame('Adebayo Oluwaseun Emmanuel', $result['data']['full_name']);
        $this->assertSame('photos/student-submissions/test.jpg', $result['data']['photo_path']);
    }

    public function test_invalid_official_student_fails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('your matric number was not found in the official student list. please contact the admin or exam officer.');

        $this->service()->registerStudent($this->validInput(['matric_no' => 'NONEXISTENT/999']));
    }

    public function test_inactive_or_missing_session_fails(): void
    {
        DB::table('exam_sessions')->where('session_id', $this->sessionId)->update(['is_active' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid or inactive session');
        $this->service()->registerStudent($this->validInput());
    }

    public function test_duplicate_registration_resumes_without_creating_another_student(): void
    {
        $this->service()->registerStudent($this->validInput());

        $result = $this->service()->registerStudent($this->validInput());

        $this->assertTrue($result['success']);
        $this->assertSame(1, DB::table('students')->where('matric_no', $this->matricNo)->count());
    }

    private function service(): RegistrationService
    {
        return new RegistrationService();
    }

    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'matric_no' => $this->matricNo,
            'session_id' => $this->sessionId,
            'photo_path' => 'photos/student-submissions/test.jpg',
        ], $overrides);
    }
}
