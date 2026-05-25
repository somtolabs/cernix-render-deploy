<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_exam_sessions_has_exactly_one_active_session(): void
    {
        $activeCount = DB::table('exam_sessions')->where('is_active', true)->count();

        $this->assertSame(1, $activeCount);
    }

    public function test_mock_sis_has_at_least_five_records(): void
    {
        $count = DB::table('mock_sis')->count();

        $this->assertGreaterThanOrEqual(1500, $count);
    }

    public function test_mock_sis_preserves_known_demo_students(): void
    {
        foreach ([
            'CSC/2021/001',
            'SEN/2021/002',
            'IFT/2021/003',
            'CYS/2021/004',
            'DTS/2021/005',
        ] as $matricNo) {
            $this->assertDatabaseHas('mock_sis', ['matric_no' => $matricNo]);
        }
    }

    public function test_mock_sis_generated_records_cover_supported_departments(): void
    {
        $departments = DB::table('mock_sis')
            ->select('department')
            ->distinct()
            ->pluck('department')
            ->all();

        $this->assertEqualsCanonicalizing([
            'Computer Science',
            'Software Engineering',
            'Information Technology',
            'Cyber Security',
            'Data Science',
        ], $departments);
    }

    public function test_mock_sis_generated_records_keep_expected_matric_format(): void
    {
        $sample = DB::table('mock_sis')
            ->inRandomOrder()
            ->limit(25)
            ->pluck('matric_no');

        foreach ($sample as $matricNo) {
            $this->assertMatchesRegularExpression('/(^[A-Z]{3}\/20\d{2}\/\d{3}$)|(^\d{9}$)/', $matricNo);
        }
    }

    public function test_mock_sis_preserves_aaua_numeric_demo_students(): void
    {
        foreach ([
            '220404001',
            '220404008',
            '220504001',
            '220504008',
            '220604001',
            '220604008',
            '220704001',
            '220704008',
            '220804001',
            '220804008',
        ] as $matricNo) {
            $this->assertDatabaseHas('mock_sis', ['matric_no' => $matricNo]);
        }
    }
}
