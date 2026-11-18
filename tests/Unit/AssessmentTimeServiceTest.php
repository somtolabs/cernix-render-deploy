<?php

namespace Tests\Unit;

use App\Services\AssessmentTimeService;
use Carbon\Carbon;
use Tests\TestCase;

class AssessmentTimeServiceTest extends TestCase
{
    private AssessmentTimeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AssessmentTimeService();
    }

    private function sampleAssessment(array $overrides = []): object
    {
        return (object) array_merge([
            'id'              => 1,
            'exam_date'       => '2026-11-17',
            'start_time'      => '09:00:00',
            'end_time'        => '12:00:00',
            'status'          => 'scheduled',
            'assessment_type' => 'exam',
            'course_code'     => 'CSC401',
            'venue'           => 'Faculty Lab 1',
        ], $overrides);
    }

    public function test_scanning_unavailable_before_early_window(): void
    {
        $exam = $this->sampleAssessment();
        $now = Carbon::parse('2026-11-17 08:29:59', 'Africa/Lagos');

        $status = $this->service->getStatus($exam, $now);

        $this->assertSame(AssessmentTimeService::STATE_UPCOMING, $status->state);
        $this->assertFalse($status->can_scan);
        $this->assertFalse($status->can_enter);
        $this->assertFalse($status->can_depart);
        $this->assertFalse($status->can_submit);
        $this->assertNull($status->scan_classification);
        $this->assertStringContainsString('8:30 AM', $status->explanation);
    }

    public function test_early_scanning_opens_exactly_30_minutes_before_start(): void
    {
        $exam = $this->sampleAssessment();
        $now = Carbon::parse('2026-11-17 08:30:00', 'Africa/Lagos');

        $status = $this->service->getStatus($exam, $now);

        $this->assertSame(AssessmentTimeService::STATE_EARLY_SCANNING, $status->state);
        $this->assertTrue($status->can_scan);
        $this->assertTrue($status->can_start_session);
        $this->assertTrue($status->can_enter);
        $this->assertFalse($status->can_depart);
        $this->assertFalse($status->can_submit);
        $this->assertSame(AssessmentTimeService::SCAN_EARLY, $status->scan_classification);
    }

    public function test_early_scanning_remains_active_until_start(): void
    {
        $exam = $this->sampleAssessment();
        $now = Carbon::parse('2026-11-17 08:59:59', 'Africa/Lagos');

        $status = $this->service->getStatus($exam, $now);

        $this->assertSame(AssessmentTimeService::STATE_EARLY_SCANNING, $status->state);
        $this->assertTrue($status->can_enter);
        $this->assertSame(AssessmentTimeService::SCAN_EARLY, $status->scan_classification);
    }

    public function test_regular_scanning_begins_exactly_at_scheduled_start(): void
    {
        $exam = $this->sampleAssessment();
        $now = Carbon::parse('2026-11-17 09:00:00', 'Africa/Lagos');

        $status = $this->service->getStatus($exam, $now);

        $this->assertSame(AssessmentTimeService::STATE_IN_PROGRESS, $status->state);
        $this->assertTrue($status->can_scan);
        $this->assertTrue($status->can_start_session);
        $this->assertTrue($status->can_enter);
        $this->assertTrue($status->can_depart);
        $this->assertTrue($status->can_submit);
        $this->assertSame(AssessmentTimeService::SCAN_REGULAR, $status->scan_classification);
    }

    public function test_regular_scanning_active_during_examination(): void
    {
        $exam = $this->sampleAssessment();
        $now = Carbon::parse('2026-11-17 10:45:00', 'Africa/Lagos');

        $status = $this->service->getStatus($exam, $now);

        $this->assertSame(AssessmentTimeService::STATE_IN_PROGRESS, $status->state);
        $this->assertTrue($status->can_scan);
        $this->assertTrue($status->can_enter);
        $this->assertTrue($status->can_depart);
        $this->assertSame(AssessmentTimeService::SCAN_REGULAR, $status->scan_classification);
    }

    public function test_late_scanning_begins_exactly_at_scheduled_end(): void
    {
        $exam = $this->sampleAssessment();
        $now = Carbon::parse('2026-11-17 12:00:00', 'Africa/Lagos');

        $status = $this->service->getStatus($exam, $now);

        $this->assertSame(AssessmentTimeService::STATE_LATE_SCANNING, $status->state);
        $this->assertTrue($status->can_scan);
        $this->assertTrue($status->can_start_session);
        $this->assertFalse($status->can_enter); // New student entry is disabled
        $this->assertTrue($status->can_depart);
        $this->assertTrue($status->can_submit);
        $this->assertSame(AssessmentTimeService::SCAN_LATE, $status->scan_classification);
    }

    public function test_late_scanning_remains_active_at_exact_closing_boundary(): void
    {
        $exam = $this->sampleAssessment();
        // 12:00 + 15 mins = 12:15:00 exact boundary
        $now = Carbon::parse('2026-11-17 12:15:00', 'Africa/Lagos');

        $status = $this->service->getStatus($exam, $now);

        $this->assertSame(AssessmentTimeService::STATE_LATE_SCANNING, $status->state);
        $this->assertTrue($status->can_scan);
        $this->assertFalse($status->can_enter);
        $this->assertTrue($status->can_depart);
        $this->assertSame(AssessmentTimeService::SCAN_LATE, $status->scan_classification);
    }

    public function test_scanning_closed_immediately_after_grace_period(): void
    {
        $exam = $this->sampleAssessment();
        $now = Carbon::parse('2026-11-17 12:15:01', 'Africa/Lagos');

        $status = $this->service->getStatus($exam, $now);

        $this->assertSame(AssessmentTimeService::STATE_ENDED, $status->state);
        $this->assertFalse($status->can_scan);
        $this->assertFalse($status->can_start_session);
        $this->assertFalse($status->can_enter);
        $this->assertFalse($status->can_depart);
        $this->assertFalse($status->can_submit);
        $this->assertNull($status->scan_classification);
    }

    public function test_completed_assessment_closes_scanning_immediately(): void
    {
        // Even during scheduled exam window (10:00 AM)
        $exam = $this->sampleAssessment(['status' => 'completed']);
        $now = Carbon::parse('2026-11-17 10:00:00', 'Africa/Lagos');

        $status = $this->service->getStatus($exam, $now);

        $this->assertSame(AssessmentTimeService::STATE_COMPLETED, $status->state);
        $this->assertFalse($status->can_scan);
        $this->assertFalse($status->can_enter);
        $this->assertNull($status->scan_classification);
    }

    public function test_cancelled_assessment_closes_scanning_immediately(): void
    {
        $exam = $this->sampleAssessment(['status' => 'cancelled']);
        $now = Carbon::parse('2026-11-17 10:00:00', 'Africa/Lagos');

        $status = $this->service->getStatus($exam, $now);

        $this->assertSame(AssessmentTimeService::STATE_CANCELLED, $status->state);
        $this->assertFalse($status->can_scan);
        $this->assertFalse($status->can_enter);
        $this->assertNull($status->scan_classification);
    }

    public function test_missing_end_time_treated_as_invalid_schedule(): void
    {
        $exam = $this->sampleAssessment(['end_time' => null]);
        $now = Carbon::parse('2026-11-17 10:00:00', 'Africa/Lagos');

        $status = $this->service->getStatus($exam, $now);

        $this->assertSame(AssessmentTimeService::STATE_INVALID_SCHEDULE, $status->state);
        $this->assertFalse($status->can_scan);
        $this->assertNotNull($status->admin_action_required);
        $this->assertStringContainsString('end time', $status->explanation);
    }

    public function test_end_time_not_after_start_time_treated_as_invalid_schedule(): void
    {
        $exam = $this->sampleAssessment(['start_time' => '10:00:00', 'end_time' => '09:00:00']);
        $now = Carbon::parse('2026-11-17 09:30:00', 'Africa/Lagos');

        $status = $this->service->getStatus($exam, $now);

        $this->assertSame(AssessmentTimeService::STATE_INVALID_SCHEDULE, $status->state);
        $this->assertFalse($status->can_scan);
        $this->assertStringContainsString('later than start time', $status->explanation);
    }

    public function test_midnight_boundary_evaluated_correctly_in_africa_lagos(): void
    {
        // 00:15 WAT on 2026-11-18 corresponds to 23:15 UTC on 2026-11-17
        $exam = $this->sampleAssessment([
            'exam_date'  => '2026-11-18',
            'start_time' => '09:00:00',
            'end_time'   => '12:00:00',
        ]);

        // Simulated UTC time: 2026-11-17 23:45:00 UTC = 2026-11-18 00:45:00 WAT
        $utcNow = Carbon::parse('2026-11-17 23:45:00', 'UTC');

        $status = $this->service->getStatus($exam, $utcNow);

        // In Africa/Lagos, it is Nov 18, 00:45. Since 00:45 < 08:30 (early window), it is upcoming.
        $this->assertSame(AssessmentTimeService::STATE_UPCOMING, $status->state);
        $this->assertFalse($status->can_scan);
    }

    public function test_all_assessment_types_follow_identical_rules(): void
    {
        $now = Carbon::parse('2026-11-17 08:45:00', 'Africa/Lagos');

        foreach (['exam', 'test', 'makeup'] as $type) {
            $assessment = $this->sampleAssessment(['assessment_type' => $type]);
            $status = $this->service->getStatus($assessment, $now);

            $this->assertSame(AssessmentTimeService::STATE_EARLY_SCANNING, $status->state, "Failed for {$type}");
            $this->assertTrue($status->can_scan, "Failed for {$type}");
            $this->assertTrue($status->can_enter, "Failed for {$type}");
            $this->assertSame(AssessmentTimeService::SCAN_EARLY, $status->scan_classification, "Failed for {$type}");
        }
    }
}
