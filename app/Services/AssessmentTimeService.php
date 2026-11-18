<?php

namespace App\Services;

use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Throwable;

class AssessmentTimeService
{
    public const TIMEZONE = 'Africa/Lagos';
    public const EARLY_WINDOW_MINUTES = 30;
    public const LATE_GRACE_MINUTES = 15;

    public const STATE_UPCOMING = 'upcoming';
    public const STATE_EARLY_SCANNING = 'early_scanning';
    public const STATE_IN_PROGRESS = 'in_progress';
    public const STATE_LATE_SCANNING = 'late_scanning';
    public const STATE_ENDED = 'ended';
    public const STATE_COMPLETED = 'completed';
    public const STATE_CANCELLED = 'cancelled';
    public const STATE_INVALID_SCHEDULE = 'invalid_schedule';

    public const SCAN_EARLY = 'early_scan';
    public const SCAN_REGULAR = 'regular_scan';
    public const SCAN_LATE = 'late_scan';

    public const TYPE_EXAM = 'exam';
    public const TYPE_TEST = 'test';
    public const TYPE_MAKEUP = 'makeup';

    /**
     * Normalize an assessment type to one of: 'exam', 'test', 'makeup'.
     * Handles naming variations safely: null, '', 'exam', 'test', 'tests',
     * 'makeup', 'make_up', 'make-up', 'makeup_exam', 'make_up_exam'.
     */
    public static function normalizeType(?string $type): string
    {
        $normalized = strtolower(trim((string) $type));
        $normalized = str_replace(['-', ' '], '_', $normalized);

        return match ($normalized) {
            'test', 'tests' => self::TYPE_TEST,
            'makeup', 'make_up', 'makeup_exam', 'make_up_exam' => self::TYPE_MAKEUP,
            default => self::TYPE_EXAM,
        };
    }

    /**
     * Check if the assessment type is a regular examination.
     */
    public static function isExam(?string $type): bool
    {
        return self::normalizeType($type) === self::TYPE_EXAM;
    }

    /**
     * Check if the assessment type is an in-semester test.
     */
    public static function isTest(?string $type): bool
    {
        return self::normalizeType($type) === self::TYPE_TEST;
    }

    /**
     * Check if the assessment type is a make-up examination.
     */
    public static function isMakeup(?string $type): bool
    {
        return self::normalizeType($type) === self::TYPE_MAKEUP;
    }

    /**
     * Get a human-readable title for the assessment category.
     */
    public static function categoryTitle(string $type, bool $short = false): string
    {
        $canonical = self::normalizeType($type);

        if ($short) {
            return match ($canonical) {
                self::TYPE_TEST => 'Test',
                self::TYPE_MAKEUP => 'Make-up',
                default => 'Exam',
            };
        }

        return match ($canonical) {
            self::TYPE_TEST => 'In-Semester Tests',
            self::TYPE_MAKEUP => 'Make-up Examinations',
            default => 'Regular Examinations',
        };
    }

    /**
     * Get the institutional operational status for an assessment.
     *
     * @param object|array|int $assessment Timetable model/stdClass, array, or timetable ID.
     * @param Carbon|null $now Optional mock/frozen Carbon instance.
     */
    public function getStatus(object|array|int $assessment, ?Carbon $now = null): AssessmentTimeStatus
    {
        $exam = $this->resolveAssessment($assessment);
        if (! $exam) {
            return new AssessmentTimeStatus(
                state: self::STATE_INVALID_SCHEDULE,
                can_scan: false,
                can_start_session: false,
                can_enter: false,
                can_depart: false,
                can_submit: false,
                scan_classification: null,
                early_window_start: null,
                start_time: null,
                end_time: null,
                late_window_end: null,
                explanation: 'Assessment record could not be found.',
                badge_label: 'Not Found',
                badge_class: 'aws-badge-invalid',
                admin_action_required: 'Assessment record missing from database.'
            );
        }

        $storedStatus = strtolower(trim((string) ($exam->status ?? 'scheduled')));
        if ($storedStatus === 'cancelled') {
            return new AssessmentTimeStatus(
                state: self::STATE_CANCELLED,
                can_scan: false,
                can_start_session: false,
                can_enter: false,
                can_depart: false,
                can_submit: false,
                scan_classification: null,
                early_window_start: null,
                start_time: null,
                end_time: null,
                late_window_end: null,
                explanation: 'This assessment has been cancelled. Scanning is disabled.',
                badge_label: 'Cancelled',
                badge_class: 'aws-badge-cancelled'
            );
        }

        if ($storedStatus === 'completed') {
            return new AssessmentTimeStatus(
                state: self::STATE_COMPLETED,
                can_scan: false,
                can_start_session: false,
                can_enter: false,
                can_depart: false,
                can_submit: false,
                scan_classification: null,
                early_window_start: null,
                start_time: null,
                end_time: null,
                late_window_end: null,
                explanation: 'This assessment has been marked as completed. Scanning is closed.',
                badge_label: 'Completed',
                badge_class: 'aws-badge-completed'
            );
        }

        $examDate = trim((string) ($exam->exam_date ?? ''));
        $startTime = trim((string) ($exam->start_time ?? ''));
        $endTime = trim((string) ($exam->end_time ?? ''));

        if ($examDate === '' || $startTime === '' || $endTime === '') {
            $missing = [];
            if ($examDate === '') {
                $missing[] = 'date';
            }
            if ($startTime === '') {
                $missing[] = 'start time';
            }
            if ($endTime === '') {
                $missing[] = 'end time';
            }

            return new AssessmentTimeStatus(
                state: self::STATE_INVALID_SCHEDULE,
                can_scan: false,
                can_start_session: false,
                can_enter: false,
                can_depart: false,
                can_submit: false,
                scan_classification: null,
                early_window_start: null,
                start_time: null,
                end_time: null,
                late_window_end: null,
                explanation: 'Assessment schedule is incomplete (' . implode(', ', $missing) . ' missing). Administrator correction required before scanning can start.',
                badge_label: 'Invalid Schedule',
                badge_class: 'aws-badge-invalid',
                admin_action_required: 'Mandatory schedule fields (' . implode(', ', $missing) . ') are missing.'
            );
        }

        $tz = new DateTimeZone(self::TIMEZONE);
        $currentNow = $now ? $now->copy()->setTimezone($tz) : Carbon::now($tz);

        try {
            $parsedStart = Carbon::parse("{$examDate} {$startTime}", self::TIMEZONE);
            $parsedEnd = Carbon::parse("{$examDate} {$endTime}", self::TIMEZONE);
        } catch (Throwable) {
            return new AssessmentTimeStatus(
                state: self::STATE_INVALID_SCHEDULE,
                can_scan: false,
                can_start_session: false,
                can_enter: false,
                can_depart: false,
                can_submit: false,
                scan_classification: null,
                early_window_start: null,
                start_time: null,
                end_time: null,
                late_window_end: null,
                explanation: 'Assessment schedule contains an unparseable date or time.',
                badge_label: 'Invalid Schedule',
                badge_class: 'aws-badge-invalid',
                admin_action_required: 'Invalid date or time format in timetable.'
            );
        }

        if ($parsedEnd->lte($parsedStart)) {
            return new AssessmentTimeStatus(
                state: self::STATE_INVALID_SCHEDULE,
                can_scan: false,
                can_start_session: false,
                can_enter: false,
                can_depart: false,
                can_submit: false,
                scan_classification: null,
                early_window_start: null,
                start_time: $parsedStart,
                end_time: $parsedEnd,
                late_window_end: null,
                explanation: 'Assessment end time must be later than start time.',
                badge_label: 'Invalid Schedule',
                badge_class: 'aws-badge-invalid',
                admin_action_required: 'End time is earlier than or equal to start time.'
            );
        }

        $earlyStart = $parsedStart->copy()->subMinutes(self::EARLY_WINDOW_MINUTES);
        $lateEnd = $parsedEnd->copy()->addMinutes(self::LATE_GRACE_MINUTES);

        // Before 30-minute early window: Unavailable / Upcoming
        if ($currentNow->lt($earlyStart)) {
            return new AssessmentTimeStatus(
                state: self::STATE_UPCOMING,
                can_scan: false,
                can_start_session: false,
                can_enter: false,
                can_depart: false,
                can_submit: false,
                scan_classification: null,
                early_window_start: $earlyStart,
                start_time: $parsedStart,
                end_time: $parsedEnd,
                late_window_end: $lateEnd,
                explanation: 'Scanning opens at ' . $earlyStart->format('g:i A') . ' (30 minutes before scheduled start time).',
                badge_label: 'Upcoming (Opens ' . $earlyStart->format('g:i A') . ')',
                badge_class: 'aws-badge-upcoming'
            );
        }

        // Early scanning: exactly 30 minutes before start_time up to before start_time
        if ($currentNow->gte($earlyStart) && $currentNow->lt($parsedStart)) {
            return new AssessmentTimeStatus(
                state: self::STATE_EARLY_SCANNING,
                can_scan: true,
                can_start_session: true,
                can_enter: true,
                can_depart: false,
                can_submit: false,
                scan_classification: self::SCAN_EARLY,
                early_window_start: $earlyStart,
                start_time: $parsedStart,
                end_time: $parsedEnd,
                late_window_end: $lateEnd,
                explanation: 'Early scanning is open for student entry and check-in until ' . $parsedStart->format('g:i A') . '.',
                badge_label: 'Early Scanning Open',
                badge_class: 'aws-badge-early'
            );
        }

        // Regular scanning: exactly at start_time up to before end_time
        if ($currentNow->gte($parsedStart) && $currentNow->lt($parsedEnd)) {
            return new AssessmentTimeStatus(
                state: self::STATE_IN_PROGRESS,
                can_scan: true,
                can_start_session: true,
                can_enter: true,
                can_depart: true,
                can_submit: true,
                scan_classification: self::SCAN_REGULAR,
                early_window_start: $earlyStart,
                start_time: $parsedStart,
                end_time: $parsedEnd,
                late_window_end: $lateEnd,
                explanation: 'Regular scanning is active for student entry and departure until ' . $parsedEnd->format('g:i A') . '.',
                badge_label: 'In Progress (Active)',
                badge_class: 'aws-badge-progress'
            );
        }

        // Late scanning: exactly at end_time up to exactly end_time + 15 minutes (inclusive)
        if ($currentNow->gte($parsedEnd) && $currentNow->lte($lateEnd)) {
            return new AssessmentTimeStatus(
                state: self::STATE_LATE_SCANNING,
                can_scan: true,
                can_start_session: true,
                can_enter: false,
                can_depart: true,
                can_submit: true,
                scan_classification: self::SCAN_LATE,
                early_window_start: $earlyStart,
                start_time: $parsedStart,
                end_time: $parsedEnd,
                late_window_end: $lateEnd,
                explanation: 'Late scanning is open for student departure and script submission only until ' . $lateEnd->format('g:i A') . '. New student entry is disabled.',
                badge_label: 'Late Scanning (Departure Only)',
                badge_class: 'aws-badge-late'
            );
        }

        // After closing boundary: Closed / Ended
        return new AssessmentTimeStatus(
            state: self::STATE_ENDED,
            can_scan: false,
            can_start_session: false,
            can_enter: false,
            can_depart: false,
            can_submit: false,
            scan_classification: null,
            early_window_start: $earlyStart,
            start_time: $parsedStart,
            end_time: $parsedEnd,
            late_window_end: $lateEnd,
            explanation: 'The scanning window closed at ' . $lateEnd->format('g:i A') . '. Scanning is no longer permitted.',
            badge_label: 'Scanning Closed',
            badge_class: 'aws-badge-ended'
        );
    }

    /**
     * Get current institutional Carbon datetime.
     */
    public function getNow(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }

    /**
     * Get today's calendar date string in Africa/Lagos.
     */
    public function getTodayDateString(): string
    {
        return Carbon::now(self::TIMEZONE)->toDateString();
    }

    /**
     * Resolve assessment input to a standard object.
     */
    private function resolveAssessment(object|array|int $assessment): ?object
    {
        if (is_int($assessment)) {
            return DB::table('timetables')->where('id', $assessment)->first();
        }

        if (is_array($assessment)) {
            return (object) $assessment;
        }

        return $assessment;
    }
}
