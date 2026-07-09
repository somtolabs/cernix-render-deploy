<?php

namespace Tests\Feature;

use App\Services\CryptoService;
use App\Services\ExamPassService;
use App\Services\QrTokenService;
use App\Services\RemitaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExamPassServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_payment_generates_course_bound_exam_pass(): void
    {
        [$student, $session, $exam] = $this->records();
        $remita = $this->createMock(RemitaService::class);

        $result = (new ExamPassService($remita, new QrTokenService(new CryptoService())))
            ->generate($student, $session, $exam, 'TEST-DEMO', 10000);

        $this->assertDatabaseHas('payment_records', [
            'student_id' => $student,
            'session_id' => $session,
        ]);
        $this->assertStringStartsWith(
            'TEST-DEMO-',
            (string) DB::table('payment_records')->where('student_id', $student)->value('rrr_number')
        );
        $this->assertDatabaseHas('qr_tokens', [
            'token_id' => $result['token_id'],
            'student_id' => $student,
            'session_id' => $session,
            'timetable_id' => $exam,
            'status' => 'UNUSED',
        ]);
    }

    public function test_payment_not_required_exam_generates_pass_without_payment_record(): void
    {
        [$student, $session, $exam] = $this->records();
        DB::table('timetables')->where('id', $exam)->update(['payment_required' => false]);

        $result = (new ExamPassService(
            $this->createMock(RemitaService::class),
            new QrTokenService(new CryptoService())
        ))->generate($student, $session, $exam, null, 0);

        $this->assertDatabaseCount('payment_records', 0);
        $this->assertDatabaseHas('qr_tokens', [
            'token_id' => $result['token_id'],
            'student_id' => $student,
            'session_id' => $session,
            'timetable_id' => $exam,
            'status' => 'UNUSED',
        ]);
    }

    public function test_exam_must_belong_to_student_department_and_level(): void
    {
        [$student, $session] = $this->records();
        $otherDepartment = DB::table('departments')->insertGetId([
            'dept_name' => 'Other Department',
            'faculty' => 'Other Faculty',
        ]);
        $otherExam = DB::table('timetables')->insertGetId([
            'exam_session_id' => $session,
            'department_id' => $otherDepartment,
            'level' => '400',
            'course_code' => 'OTH401',
            'course_title' => 'Other Paper',
            'exam_date' => today()->addDay(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => 'Other Hall',
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Select a valid assigned course');

        (new ExamPassService($this->createMock(RemitaService::class), new QrTokenService(new CryptoService())))
            ->generate($student, $session, $otherExam, 'TEST-PASS-2', 10000);
    }

    public function test_qr_generation_remains_compatible_before_timetable_column_migration(): void
    {
        [$student, $session, $exam] = $this->records();

        Schema::disableForeignKeyConstraints();
        Schema::table('qr_tokens', function (Blueprint $table) {
            $table->dropForeign(['timetable_id']);
            $table->dropIndex('qr_tokens_exam_lookup');
            $table->dropUnique('qr_tokens_student_session_timetable_unique');
            $table->dropColumn('timetable_id');
        });
        Schema::enableForeignKeyConstraints();

        $result = (new ExamPassService(
            $this->createMock(RemitaService::class),
            new QrTokenService(new CryptoService())
        ))->generate($student, $session, $exam, 'TEST-DEMO', 10000);

        $this->assertDatabaseHas('qr_tokens', [
            'token_id' => $result['token_id'],
            'student_id' => $student,
            'session_id' => $session,
            'status' => 'UNUSED',
        ]);
    }

    public function test_test_demo_is_student_scoped_and_repeat_generation_reuses_active_pass(): void
    {
        [$student, $session, $exam] = $this->records();
        $service = new ExamPassService(
            $this->createMock(RemitaService::class),
            new QrTokenService(new CryptoService())
        );

        $first = $service->generate($student, $session, $exam, 'TEST-DEMO', 10000);
        $repeat = $service->generate($student, $session, $exam, 'TEST-DEMO', 10000);

        $department = DB::table('students')->where('matric_no', $student)->value('department_id');
        $secondStudent = 'CSC/2021/002';
        DB::table('students')->insert([
            'matric_no' => $secondStudent,
            'full_name' => 'Second Demo Student',
            'department_id' => $department,
            'level' => '400',
            'session_id' => $session,
            'photo_path' => 'demo-passports/student-002.jpg',
            'photo_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('official_students')->insert([
            'matric_number' => $secondStudent,
            'full_name' => 'Second Demo Student',
            'department' => 'Computer Science',
            'faculty' => 'Faculty of Computing',
            'level' => '400',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $second = $service->generate($secondStudent, $session, $exam, 'TEST-DEMO', 10000);

        $this->assertSame($first['token_id'], $repeat['token_id']);
        $this->assertNotSame($first['token_id'], $second['token_id']);
        $this->assertDatabaseCount('payment_records', 2);
        $this->assertDatabaseCount('qr_tokens', 2);
        $this->assertCount(2, DB::table('payment_records')->pluck('rrr_number')->unique());
    }

    public function test_verified_session_payment_is_reused_for_another_course_without_another_rrr(): void
    {
        [$student, $session, $firstExam] = $this->records();
        $service = new ExamPassService(
            $this->createMock(RemitaService::class),
            new QrTokenService(new CryptoService())
        );

        $service->generate($student, $session, $firstExam, 'TEST-DEMO', 10000);

        $department = DB::table('students')->where('matric_no', $student)->value('department_id');
        $secondExam = DB::table('timetables')->insertGetId([
            'exam_session_id' => $session,
            'department_id' => $department,
            'level' => '400',
            'course_code' => 'CSC402',
            'course_title' => 'Compiler Construction',
            'exam_date' => today()->addDays(2),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => 'CBT Hall B',
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondPass = $service->generate($student, $session, $secondExam, null, 10000);

        $this->assertDatabaseCount('payment_records', 1);
        $this->assertDatabaseHas('payment_records', [
            'student_id' => $student,
            'session_id' => $session,
        ]);
        $this->assertDatabaseHas('qr_tokens', [
            'token_id' => $secondPass['token_id'],
            'student_id' => $student,
            'session_id' => $session,
            'timetable_id' => $secondExam,
        ]);
        $this->assertSame(2, DB::table('qr_tokens')->where('student_id', $student)->count());
    }

    public function test_used_course_pass_cannot_be_generated_again(): void
    {
        [$student, $session, $exam] = $this->records();
        $service = new ExamPassService(
            $this->createMock(RemitaService::class),
            new QrTokenService(new CryptoService())
        );

        $pass = $service->generate($student, $session, $exam, 'TEST-DEMO', 10000);
        DB::table('qr_tokens')->where('token_id', $pass['token_id'])->update([
            'status' => 'USED',
            'used_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already been used');

        try {
            $service->generate($student, $session, $exam, null, 10000);
        } finally {
            $this->assertSame(1, DB::table('payment_records')->where('student_id', $student)->count());
            $this->assertSame(1, DB::table('qr_tokens')->where('student_id', $student)->count());
        }
    }

    private function records(): array
    {
        $department = DB::table('departments')->insertGetId([
            'dept_name' => 'Computer Science',
            'faculty' => 'Faculty of Computing',
        ]);
        $session = DB::table('exam_sessions')->insertGetId([
            'semester' => 'First Semester',
            'academic_year' => '2025/2026',
            'fee_amount' => 10000,
            'aes_key' => bin2hex(random_bytes(32)),
            'hmac_secret' => bin2hex(random_bytes(32)),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $student = 'CSC/2021/001';
        DB::table('official_students')->insert([
            'matric_number' => $student,
            'full_name' => 'Test Student',
            'department' => 'Computer Science',
            'faculty' => 'Faculty of Computing',
            'level' => '400',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('students')->insert([
            'matric_no' => $student,
            'full_name' => 'Test Student',
            'department_id' => $department,
            'level' => '400',
            'session_id' => $session,
            'photo_path' => 'demo-passports/student-020.jpg',
            'photo_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $exam = DB::table('timetables')->insertGetId([
            'exam_session_id' => $session,
            'department_id' => $department,
            'level' => '400',
            'course_code' => 'CSC401',
            'course_title' => 'Artificial Intelligence',
            'exam_date' => today()->addDay(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => 'CBT Hall A',
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$student, $session, $exam];
    }
}
