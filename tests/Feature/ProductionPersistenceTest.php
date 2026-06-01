<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Database\Seeders\DepartmentsSeeder;
use Tests\TestCase;

class ProductionPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_safe_migration_and_idempotent_seeders_preserve_runtime_activity(): void
    {
        $this->seed();

        $departmentId = DB::table('departments')->where('dept_name', 'Computer Science')->value('dept_id');
        $defaultSessionId = DB::table('exam_sessions')->where('is_active', true)->value('session_id');
        $examinerId = DB::table('examiners')->where('username', 'examiner1')->value('examiner_id');

        DB::table('exam_sessions')->where('session_id', $defaultSessionId)->update(['is_active' => false]);

        $runtimeSessionId = DB::table('exam_sessions')->insertGetId([
            'semester' => 'Second Semester',
            'academic_year' => '2026/2027',
            'fee_amount' => 120000.00,
            'aes_key' => 'runtime-session-aes-key',
            'hmac_secret' => 'runtime-session-hmac-secret',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $matricNo = 'RUNTIME/2026/999';
        $tokenId = (string) Str::uuid();

        DB::table('students')->insert([
            'matric_no' => $matricNo,
            'full_name' => 'Runtime Persistence Student',
            'department_id' => $departmentId,
            'session_id' => $runtimeSessionId,
            'level' => '400',
            'department_code' => '04',
            'faculty_code' => '04',
            'photo_path' => 'demo-passports/student-008.jpg',
            'created_at' => now(),
        ]);

        DB::table('payment_records')->insert([
            'student_id' => $matricNo,
            'rrr_number' => 'TEST-PERSISTENCE-999',
            'amount_declared' => 120000.00,
            'amount_confirmed' => 120000.00,
            'remita_response' => json_encode(['status' => 'verified']),
            'verified_at' => now(),
        ]);

        DB::table('qr_tokens')->insert([
            'token_id' => $tokenId,
            'student_id' => $matricNo,
            'session_id' => $runtimeSessionId,
            'encrypted_payload' => 'runtime-encrypted-value',
            'hmac_signature' => 'runtime-signature-value',
            'status' => 'USED',
            'issued_at' => now()->subMinute(),
            'used_at' => now(),
        ]);

        $verificationLogId = DB::table('verification_logs')->insertGetId([
            'token_id' => $tokenId,
            'examiner_id' => $examinerId,
            'decision' => 'APPROVED',
            'timestamp' => now(),
            'device_fp' => 'runtime-device',
            'ip_address' => '127.0.0.1',
        ]);

        $auditLogId = DB::table('audit_log')->insertGetId([
            'actor_id' => (string) $examinerId,
            'actor_type' => 'examiner',
            'action' => 'runtime.persistence.checked',
            'target_type' => 'student',
            'target_id' => $matricNo,
            'metadata' => json_encode(['test' => true]),
            'timestamp' => now(),
        ]);

        $noteId = DB::table('admin_notes')->insertGetId([
            'actor_name' => 'Persistence Test',
            'entity_type' => 'student',
            'entity_id' => $matricNo,
            'note_type' => 'review',
            'visibility' => 'internal',
            'note' => 'Runtime note must remain after startup-safe operations.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('migrate', ['--force' => true]);
        $this->seed();

        $this->assertDatabaseHas('students', ['matric_no' => $matricNo]);
        $this->assertDatabaseHas('payment_records', ['student_id' => $matricNo]);
        $this->assertDatabaseHas('qr_tokens', ['token_id' => $tokenId]);
        $this->assertDatabaseHas('verification_logs', ['log_id' => $verificationLogId]);
        $this->assertDatabaseHas('audit_log', ['id' => $auditLogId]);
        $this->assertDatabaseHas('admin_notes', ['note_id' => $noteId]);
        $this->assertDatabaseHas('exam_sessions', ['session_id' => $runtimeSessionId, 'is_active' => true]);
        $this->assertDatabaseHas('exam_sessions', ['session_id' => $defaultSessionId, 'is_active' => false]);
    }

    public function test_render_startup_is_migration_only_with_explicit_seed_opt_in(): void
    {
        $script = file_get_contents(base_path('scripts/render-start.sh'));

        $this->assertStringContainsString('php artisan migrate --force', $script);
        $this->assertStringContainsString('php artisan cernix:ensure-baseline-data', $script);
        $this->assertStringContainsString('CERNIX_SEED_ON_BOOT', $script);
        $this->assertStringContainsString('DB_CONNECTION:-', $script);
        $this->assertStringContainsString('DATABASE_URL:-${DB_URL:-}', $script);
        $this->assertStringContainsString('APP_KEY:-', $script);
        $this->assertStringContainsString('APP_JWT_SECRET:-${JWT_SECRET:-}', $script);
        $this->assertStringNotContainsString('RENDER_SKIP_SEED', $script);
        $this->assertStringNotContainsString('migrate:fresh', $script);
        $this->assertStringNotContainsString('migrate:refresh', $script);
        $this->assertStringNotContainsString('db:wipe', $script);
    }

    public function test_baseline_department_seeder_restores_reference_rows_without_removing_runtime_activity(): void
    {
        $this->seed();

        DB::table('departments')->where('dept_name', 'Data Science')->delete();
        $runtimeStudent = DB::table('students')->where('matric_no', '220404008')->first();

        $this->seed(DepartmentsSeeder::class);

        $this->assertDatabaseHas('departments', [
            'dept_name' => 'Data Science',
            'faculty' => 'Faculty of Computing',
            'department_code' => '08',
            'faculty_code' => '04',
        ]);

        if ($runtimeStudent) {
            $this->assertDatabaseHas('students', ['matric_no' => $runtimeStudent->matric_no]);
        }
    }

    public function test_production_reset_is_blocked_without_explicit_override(): void
    {
        $this->seed();
        app()->detectEnvironment(fn () => 'production');
        $this->setResetOverride('false');

        try {
            $exitCode = Artisan::call('cernix:reset', ['--force' => true]);

            $this->assertSame(1, $exitCode);
            $this->assertDatabaseHas('examiners', ['username' => 'examiner1']);
        } finally {
            app()->detectEnvironment(fn () => 'testing');
            $this->setResetOverride(null);
        }
    }

    private function setResetOverride(?string $value): void
    {
        if ($value === null) {
            putenv('CERNIX_ALLOW_PRODUCTION_RESET');
            unset($_ENV['CERNIX_ALLOW_PRODUCTION_RESET'], $_SERVER['CERNIX_ALLOW_PRODUCTION_RESET']);

            return;
        }

        putenv("CERNIX_ALLOW_PRODUCTION_RESET={$value}");
        $_ENV['CERNIX_ALLOW_PRODUCTION_RESET'] = $value;
        $_SERVER['CERNIX_ALLOW_PRODUCTION_RESET'] = $value;
    }
}
