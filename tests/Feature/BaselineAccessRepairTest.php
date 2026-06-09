<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ExaminersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BaselineAccessRepairTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_baseline_staff_repair_corrects_stale_login_fields_without_touching_runtime_activity(): void
    {
        $adminUser = User::factory()->create();
        $createdAt = now()->subYears(2)->startOfSecond();
        $lastActiveAt = now()->subDay()->startOfSecond();

        DB::table('examiners')->where('username', 'examiner1')->update([
            'full_name' => 'Stale Examiner',
            'password_hash' => Hash::make('wrong-password'),
            'role' => 'admin',
            'is_active' => false,
            'admin_user_id' => $adminUser->id,
            'last_active_at' => $lastActiveAt,
            'created_at' => $createdAt,
        ]);

        DB::table('examiners')->where('username', 'admin1')->update([
            'full_name' => 'Stale Admin',
            'password_hash' => 'admin123',
            'is_active' => false,
        ]);

        DB::table('examiners')->where('username', 'superadmin')->update([
            'full_name' => 'Stale Super Admin',
            'password_hash' => Hash::make('wrong-password'),
            'role' => 'examiner',
            'is_active' => false,
        ]);

        $noteId = DB::table('admin_notes')->insertGetId([
            'actor_name' => 'Runtime Activity',
            'entity_type' => 'student',
            'entity_id' => 'RUNTIME/2026/STAFF',
            'note_type' => 'review',
            'visibility' => 'internal',
            'note' => 'Baseline account repair must not remove runtime activity.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seed(ExaminersSeeder::class);

        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();
        $admin = DB::table('examiners')->where('username', 'admin1')->first();
        $superAdmin = DB::table('examiners')->where('username', 'superadmin')->first();

        $this->assertSame('Examiner One', $examiner->full_name);
        $this->assertSame('examiner', $examiner->role);
        $this->assertTrue((bool) $examiner->is_active);
        $this->assertTrue(Hash::check('password123', $examiner->password_hash));
        $this->assertSame($adminUser->id, $examiner->admin_user_id);
        $this->assertSame($createdAt->toDateTimeString(), Carbon::parse($examiner->created_at)->toDateTimeString());
        $this->assertSame($lastActiveAt->toDateTimeString(), Carbon::parse($examiner->last_active_at)->toDateTimeString());

        $this->assertSame('Admin One', $admin->full_name);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue((bool) $admin->is_active);
        $this->assertTrue(Hash::check('admin123', $admin->password_hash));

        $this->assertSame('Super Admin', $superAdmin->full_name);
        $this->assertSame('super_admin', $superAdmin->role);
        $this->assertTrue((bool) $superAdmin->is_active);
        $this->assertTrue(Hash::check('superadmin123', $superAdmin->password_hash));

        $this->assertDatabaseHas('admin_notes', ['note_id' => $noteId]);
    }

    public function test_baseline_repair_command_creates_missing_staff_accounts(): void
    {
        DB::table('examiners')->whereIn('username', [
            'examiner1',
            'admin1',
            'superadmin',
        ])->delete();

        $this->assertSame(0, Artisan::call('cernix:repair-baseline', ['--force' => true]));

        $expected = [
            'examiner1' => ['password123', 'examiner'],
            'admin1' => ['admin123', 'admin'],
            'superadmin' => ['superadmin123', 'super_admin'],
        ];

        foreach ($expected as $username => [$password, $role]) {
            $account = DB::table('examiners')->where('username', $username)->first();

            $this->assertNotNull($account);
            $this->assertSame($role, $account->role);
            $this->assertTrue((bool) $account->is_active);
            $this->assertTrue(Hash::check($password, $account->password_hash));
        }
    }

    public function test_baseline_repair_command_is_idempotent_and_does_not_duplicate_timetables(): void
    {
        Artisan::call('cernix:repair-baseline', ['--force' => true]);

        $accountCount = DB::table('examiners')
            ->whereIn('username', ['examiner1', 'admin1', 'superadmin'])
            ->count();
        $timetableCount = DB::table('timetables')->count();
        $passwordHashes = DB::table('examiners')
            ->whereIn('username', ['examiner1', 'admin1', 'superadmin'])
            ->pluck('password_hash', 'username')
            ->all();

        Artisan::call('cernix:repair-baseline', ['--force' => true]);

        $this->assertSame(3, $accountCount);
        $this->assertSame($accountCount, DB::table('examiners')
            ->whereIn('username', ['examiner1', 'admin1', 'superadmin'])
            ->count());
        $this->assertSame($timetableCount, DB::table('timetables')->count());
        $this->assertSame($passwordHashes, DB::table('examiners')
            ->whereIn('username', ['examiner1', 'admin1', 'superadmin'])
            ->pluck('password_hash', 'username')
            ->all());
    }

    public function test_baseline_repair_uses_update_or_insert_for_staff_accounts(): void
    {
        $source = file_get_contents(app_path('Services/BaselineAccessService.php'));

        $this->assertStringContainsString('updateOrInsert(', $source);
        $this->assertStringNotContainsString('firstOrCreate(', $source);
    }

    public function test_baseline_access_command_reports_usernames_without_printing_passwords(): void
    {
        $exitCode = Artisan::call('cernix:ensure-baseline-access');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('examiner1', $output);
        $this->assertStringContainsString('admin1', $output);
        $this->assertStringContainsString('superadmin', $output);
        $this->assertStringNotContainsString('password123', $output);
        $this->assertStringNotContainsString('admin123', $output);
        $this->assertStringNotContainsString('superadmin123', $output);
    }
}
