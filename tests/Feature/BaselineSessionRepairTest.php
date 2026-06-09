<?php

namespace Tests\Feature;

use App\Services\BaselineSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BaselineSessionRepairTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_baseline_data_command_creates_an_active_session_when_none_exists(): void
    {
        DB::table('exam_sessions')->delete();

        $this->assertSame(0, Artisan::call('cernix:repair-baseline', ['--force' => true]));

        $this->assertDatabaseHas('exam_sessions', [
            'semester' => 'First Semester',
            'academic_year' => '2025/2026',
            'is_active' => true,
        ]);
    }

    public function test_baseline_session_repair_activates_existing_baseline_only_when_no_session_is_active(): void
    {
        DB::table('exam_sessions')->update(['is_active' => false]);
        $baseline = DB::table('exam_sessions')->where('academic_year', '2025/2026')->first();

        app(BaselineSessionService::class)->ensure();

        $this->assertDatabaseHas('exam_sessions', ['session_id' => $baseline->session_id, 'is_active' => true]);
    }

    public function test_baseline_session_repair_preserves_existing_keys_and_custom_active_session(): void
    {
        $baseline = DB::table('exam_sessions')->where('academic_year', '2025/2026')->first();
        $aesKey = $baseline->aes_key;
        $hmacSecret = $baseline->hmac_secret;

        DB::table('exam_sessions')->where('session_id', $baseline->session_id)->update(['is_active' => false]);
        $customSessionId = DB::table('exam_sessions')->insertGetId([
            'semester' => 'Second Semester',
            'academic_year' => '2026/2027',
            'fee_amount' => 125000.00,
            'aes_key' => 'custom-aes-key',
            'hmac_secret' => 'custom-hmac-secret',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(BaselineSessionService::class)->ensure();
        $baseline = DB::table('exam_sessions')->where('session_id', $baseline->session_id)->first();

        $this->assertSame($aesKey, $baseline->aes_key);
        $this->assertSame($hmacSecret, $baseline->hmac_secret);
        $this->assertFalse((bool) $baseline->is_active);
        $this->assertDatabaseHas('exam_sessions', ['session_id' => $customSessionId, 'is_active' => true]);
    }

    public function test_baseline_data_command_does_not_remove_runtime_activity(): void
    {
        $noteId = DB::table('admin_notes')->insertGetId([
            'actor_name' => 'Runtime Activity',
            'entity_type' => 'student',
            'entity_id' => 'RUNTIME/SESSION/001',
            'note_type' => 'review',
            'visibility' => 'internal',
            'note' => 'Baseline session repair must not remove runtime activity.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('cernix:repair-baseline', ['--force' => true]);

        $this->assertDatabaseHas('admin_notes', ['note_id' => $noteId]);
    }

    public function test_registration_page_has_an_active_session_after_baseline_data_repair(): void
    {
        DB::table('exam_sessions')->update(['is_active' => false]);
        Artisan::call('cernix:repair-baseline', ['--force' => true]);

        $this->get('/student/register')
            ->assertOk()
            ->assertDontSee('No active exam session is currently open.')
            ->assertSee('First Semester');
    }

    public function test_baseline_repair_restores_departments_and_missing_timetable_rows(): void
    {
        DB::table('timetables')->delete();
        DB::table('departments')->delete();

        $this->assertSame(0, Artisan::call('cernix:repair-baseline', ['--force' => true]));

        $this->assertSame(5, DB::table('departments')->count());
        $this->assertSame(20, DB::table('timetables')->count());

        foreach (['100', '200', '300', '400'] as $level) {
            $this->assertDatabaseHas('timetables', [
                'level' => $level,
                'status' => 'scheduled',
            ]);
        }
    }
}
