<?php

namespace Tests\Feature;

use App\Services\RiskIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminManagementWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_super_admin_can_create_active_examiner_that_persists_and_can_login(): void
    {
        $super = DB::table('examiners')->where('username', 'superadmin')->first();
        $username = 'qa_examiner_' . Str::lower(Str::random(6));

        $this->withSession($this->adminSession($super))
            ->post(route('admin.examiners.store'), [
                'full_name' => 'QA Persistence Examiner',
                'username' => $username,
                'password' => 'strongpass123',
                'role' => 'examiner',
            ])
            ->assertRedirect();

        $examiner = DB::table('examiners')->where('username', $username)->first();

        $this->assertNotNull($examiner);
        $this->assertSame('examiner', $examiner->role);
        $this->assertTrue((bool) $examiner->is_active);
        $this->assertTrue(Hash::check('strongpass123', $examiner->password_hash));

        $this->withSession($this->adminSession($super))
            ->get(route('admin.examiners'))
            ->assertOk()
            ->assertSee('QA Persistence Examiner')
            ->assertSee($username);

        $this->postJson('/examiner/login', [
            'username' => $username,
            'password' => 'strongpass123',
        ])->assertOk()
            ->assertJsonPath('redirect_url', '/examiner/dashboard');

        $this->withSession($this->adminSession($examiner))
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_created_examiner_remains_visible_after_refresh(): void
    {
        $admin = DB::table('examiners')->where('username', 'admin1')->first();
        $username = 'admin_examiner_' . Str::lower(Str::random(6));

        $this->withSession($this->adminSession($admin))
            ->post(route('admin.examiners.store'), [
                'full_name' => 'Admin Created Examiner',
                'username' => $username,
                'password' => 'strongpass123',
                'role' => 'examiner',
            ])
            ->assertRedirect();

        $examiner = DB::table('examiners')->where('username', $username)->first();
        $this->assertNotNull($examiner);

        if (Schema::hasColumn('examiners', 'admin_user_id')) {
            $this->assertNull($examiner->admin_user_id);
        }

        $this->withSession($this->adminSession($admin))
            ->get(route('admin.examiners'))
            ->assertOk()
            ->assertSee('Admin Created Examiner')
            ->assertSee('View');
    }

    public function test_student_list_and_view_show_clean_identity_without_qr_internals(): void
    {
        $admin = DB::table('examiners')->where('username', 'admin1')->first();
        $student = $this->createStudentRecord();

        $this->withSession($this->adminSession($admin))
            ->get(route('admin.students'))
            ->assertOk()
            ->assertSee($student['full_name'])
            ->assertSee($student['matric_no'])
            ->assertSee('View');

        $this->withSession($this->adminSession($admin))
            ->get(route('admin.students.show', ['student' => $student['matric_no']]))
            ->assertOk()
            ->assertSee('Identity and Access')
            ->assertSee('Payment')
            ->assertSee('Exam Access')
            ->assertSee('Scan Summary')
            ->assertSee($student['full_name'])
            ->assertSee($student['matric_no'])
            ->assertDontSee('encrypted_payload')
            ->assertDontSee('hmac_signature')
            ->assertDontSee('aes_key')
            ->assertDontSee('hmac_secret');
    }

    public function test_admin_intelligence_page_does_not_show_developer_paths_or_commands(): void
    {
        $admin = DB::table('examiners')->where('username', 'admin1')->first();

        $this->withSession($this->adminSession($admin))
            ->get(route('admin.intelligence'))
            ->assertOk()
            ->assertSee('Risk Intelligence')
            ->assertSee('Total Scans')
            ->assertDontSee('QR token')
            ->assertDontSee('Token Reference')
            ->assertDontSee('Token Ref')
            ->assertDontSee('Token Status')
            ->assertDontSee('JSON Path')
            ->assertDontSee('HTML Report Path')
            ->assertDontSee('storage/app/risk-analysis')
            ->assertDontSee('php artisan cernix')
            ->assertDontSee('python_services/risk_analyzer');
    }

    public function test_live_risk_intelligence_detects_repeated_student_scan(): void
    {
        $student = $this->createStudentRecord();
        $this->addDuplicateScanAttempts($student['token_id'], $student['examiner_id']);

        $model = app(RiskIntelligenceService::class)->viewModel();
        $riskRows = collect($model['high_risk_students'] ?? []);
        $studentRisk = $riskRows->firstWhere('matric_no', $student['matric_no']);

        $this->assertSame('live', $model['source']);
        $this->assertGreaterThanOrEqual(3, $model['summary']['total_scans']);
        $this->assertGreaterThanOrEqual(2, $model['summary']['duplicate_count']);
        $this->assertNotNull($studentRisk);
        $this->assertGreaterThan(0, $studentRisk['score']);
        $this->assertGreaterThanOrEqual(2, $studentRisk['duplicate_count']);
        $this->assertNotEmpty($studentRisk['reasons']);
    }

    public function test_admin_intelligence_page_displays_live_repeated_scan_risk(): void
    {
        $admin = DB::table('examiners')->where('username', 'admin1')->first();
        $student = $this->createStudentRecord();
        $this->addDuplicateScanAttempts($student['token_id'], $student['examiner_id']);

        $this->withSession($this->adminSession($admin))
            ->get(route('admin.intelligence'))
            ->assertOk()
            ->assertSee('Live System Summary')
            ->assertSee($student['full_name'])
            ->assertSee($student['matric_no'])
            ->assertSee('repeated scan attempt')
            ->assertSee('View Student')
            ->assertSee('View more')
            ->assertDontSee('QR token')
            ->assertDontSee('Token Reference')
            ->assertDontSee('127.0.0.1')
            ->assertDontSee('JSON Path')
            ->assertDontSee('HTML Report Path')
            ->assertDontSee('storage/app/risk-analysis')
            ->assertDontSee('php artisan cernix')
            ->assertDontSee('python_services/risk_analyzer');
    }

    public function test_admin_dashboard_risk_card_uses_live_duplicate_metrics(): void
    {
        $admin = DB::table('examiners')->where('username', 'admin1')->first();
        $student = $this->createStudentRecord();
        $this->addDuplicateScanAttempts($student['token_id'], $student['examiner_id']);

        $this->withSession($this->adminSession($admin))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Live System Summary')
            ->assertSee('3 scans')
            ->assertSee('2 repeated');
    }

    public function test_student_warning_badges_and_profile_review_card_render(): void
    {
        $admin = DB::table('examiners')->where('username', 'admin1')->first();
        $student = $this->createStudentRecord();
        $this->addDuplicateScanAttempts($student['token_id'], $student['examiner_id']);

        $this->withSession($this->adminSession($admin))
            ->get(route('admin.students'))
            ->assertOk()
            ->assertSee('Needs Review')
            ->assertSee($student['full_name'])
            ->assertSee($student['matric_no'])
            ->assertDontSee('Token Reference')
            ->assertDontSee('Token Ref')
            ->assertDontSee('127.0.0.1');

        $this->withSession($this->adminSession($admin))
            ->get(route('admin.students.show', ['student' => $student['matric_no']]))
            ->assertOk()
            ->assertSee('Review Status')
            ->assertSee('Repeated Scans')
            ->assertSee('This exam pass was scanned again')
            ->assertDontSee('Token Reference')
            ->assertDontSee('127.0.0.1');
    }

    public function test_examiner_warning_appears_without_network_or_token_details(): void
    {
        $super = DB::table('examiners')->where('username', 'superadmin')->first();
        $student = $this->createStudentRecord();
        $this->addDuplicateScanAttempts($student['token_id'], $student['examiner_id']);

        $this->withSession($this->adminSession($super))
            ->get(route('admin.examiners'))
            ->assertOk()
            ->assertSee('Needs Review')
            ->assertSee('Repeated')
            ->assertDontSee('Token Reference')
            ->assertDontSee('127.0.0.1');

        $this->withSession($this->adminSession($super))
            ->get(route('admin.examiners.show', ['examiner' => $student['examiner_id']]))
            ->assertOk()
            ->assertSee('Review Status')
            ->assertSee('Repeated Scans')
            ->assertSee('repeated scan attempts recorded')
            ->assertDontSee('Token Reference')
            ->assertDontSee('127.0.0.1');
    }

    public function test_admin_scan_log_pages_hide_token_numbers_and_raw_ip_by_default(): void
    {
        $admin = DB::table('examiners')->where('username', 'admin1')->first();
        $student = $this->createStudentRecord();
        $this->addDuplicateScanAttempts($student['token_id'], $student['examiner_id']);
        $logId = DB::table('verification_logs')->orderByDesc('log_id')->value('log_id');

        $this->withSession($this->adminSession($admin))
            ->get(route('admin.scan-logs'))
            ->assertOk()
            ->assertSee('Review Status')
            ->assertDontSee('Token')
            ->assertDontSee('127.0.0.1');

        $this->withSession($this->adminSession($admin))
            ->get(route('admin.scan-logs.show', ['log' => $logId]))
            ->assertOk()
            ->assertSee('Exam Pass')
            ->assertSee('Repeated scan needs review')
            ->assertDontSee('Token Reference')
            ->assertDontSee('127.0.0.1');
    }

    public function test_admin_settings_does_not_show_placeholder_cards_for_admin(): void
    {
        $admin = DB::table('examiners')->where('username', 'admin1')->first();

        $this->withSession($this->adminSession($admin))
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('No editable settings are available for this role.')
            ->assertDontSee('Scanner Settings')
            ->assertDontSee('QR / Verification Rules')
            ->assertDontSee('Role / Access Overview')
            ->assertDontSee('Maintenance / Cache Info')
            ->assertDontSee('Active Session');
    }

    public function test_admin_intelligence_is_compact_and_has_no_report_status(): void
    {
        $admin = DB::table('examiners')->where('username', 'admin1')->first();
        $student = $this->createStudentRecord();
        $this->addDuplicateScanAttempts($student['token_id'], $student['examiner_id']);

        $this->withSession($this->adminSession($admin))
            ->get(route('admin.intelligence'))
            ->assertOk()
            ->assertSee('Repeated')
            ->assertSee('View Student')
            ->assertSee('View more')
            ->assertDontSee('Report Status')
            ->assertDontSee('JSON Path')
            ->assertDontSee('HTML Report Path')
            ->assertDontSee('storage/app/risk-analysis')
            ->assertDontSee('php artisan');
    }

    public function test_user_facing_pages_hide_raw_rrr_after_registration(): void
    {
        $admin = DB::table('examiners')->where('username', 'admin1')->first();
        $student = $this->createStudentRecord();

        $pages = [
            route('admin.dashboard'),
            route('admin.students'),
            route('admin.students.show', ['student' => $student['matric_no']]),
            route('admin.payments'),
            route('admin.payments.student.show', ['student' => $student['matric_no']]),
            route('admin.scan-logs'),
        ];

        foreach ($pages as $url) {
            $this->withSession($this->adminSession($admin))
                ->get($url)
                ->assertOk()
                ->assertDontSee('TEST-ADMIN-VIEW')
                ->assertDontSee('RRR');
        }
    }

    private function adminSession(object $account): array
    {
        return [
            'examiner_id' => (int) $account->examiner_id,
            'examiner_username' => $account->username,
            'examiner_name' => $account->full_name,
            'examiner_role' => $account->role,
        ];
    }

    private function createStudentRecord(): array
    {
        $department = DB::table('departments')->where('dept_name', 'Computer Science')->first();
        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();
        $matric = '220404014';
        $token = (string) Str::uuid();

        $student = [
            'matric_no' => $matric,
            'full_name' => 'Samuel Akinwale Bello',
            'department_id' => $department->dept_id,
            'session_id' => $session->session_id,
            'photo_path' => 'demo-passports/student-014.jpg',
            'created_at' => now(),
        ];

        foreach (['level' => '400', 'department_code' => '04', 'faculty_code' => '04'] as $column => $value) {
            if (Schema::hasColumn('students', $column)) {
                $student[$column] = $value;
            }
        }

        DB::table('students')->updateOrInsert(['matric_no' => $matric], $student);
        DB::table('payment_records')->updateOrInsert(
            ['rrr_number' => 'TEST-ADMIN-VIEW'],
            [
                'student_id' => $matric,
                'amount_declared' => 100000,
                'amount_confirmed' => 100000,
                'remita_response' => json_encode(['status' => 'Verified Demo Payment', 'source' => 'demo']),
                'verified_at' => now(),
            ]
        );
        DB::table('qr_tokens')->updateOrInsert(
            ['token_id' => $token],
            [
                'student_id' => $matric,
                'session_id' => $session->session_id,
                'encrypted_payload' => 'not-rendered-payload',
                'hmac_signature' => 'not-rendered-signature',
                'status' => 'UNUSED',
                'issued_at' => now(),
                'used_at' => null,
            ]
        );
        DB::table('verification_logs')->insert([
            'token_id' => $token,
            'examiner_id' => $examiner->examiner_id,
            'decision' => 'APPROVED',
            'timestamp' => now(),
            'device_fp' => 'test-device',
            'ip_address' => '127.0.0.1',
        ]);

        return $student + [
            'token_id' => $token,
            'examiner_id' => (int) $examiner->examiner_id,
        ];
    }

    private function addDuplicateScanAttempts(string $tokenId, int $examinerId): void
    {
        DB::table('qr_tokens')
            ->where('token_id', $tokenId)
            ->update(['status' => 'USED', 'used_at' => now()->subMinutes(2)]);

        foreach ([90, 30] as $secondsAgo) {
            DB::table('verification_logs')->insert([
                'token_id' => $tokenId,
                'examiner_id' => $examinerId,
                'decision' => 'DUPLICATE',
                'timestamp' => now()->subSeconds($secondsAgo),
                'device_fp' => 'test-device',
                'ip_address' => '127.0.0.1',
            ]);
        }
    }
}
