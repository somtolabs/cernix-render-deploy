<?php

namespace App\Services;

use Carbon\Carbon;

class AssessmentTimeStatus
{
    public function __construct(
        public readonly string $state,
        public readonly bool $can_scan,
        public readonly bool $can_start_session,
        public readonly bool $can_enter,
        public readonly bool $can_depart,
        public readonly bool $can_submit,
        public readonly ?string $scan_classification,
        public readonly ?Carbon $early_window_start,
        public readonly ?Carbon $start_time,
        public readonly ?Carbon $end_time,
        public readonly ?Carbon $late_window_end,
        public readonly string $explanation,
        public readonly string $badge_label,
        public readonly string $badge_class,
        public readonly ?string $admin_action_required = null,
    ) {}

    public function isUpcoming(): bool
    {
        return $this->state === AssessmentTimeService::STATE_UPCOMING;
    }

    public function isEarlyScanning(): bool
    {
        return $this->state === AssessmentTimeService::STATE_EARLY_SCANNING;
    }

    public function isInProgress(): bool
    {
        return $this->state === AssessmentTimeService::STATE_IN_PROGRESS;
    }

    public function isLateScanning(): bool
    {
        return $this->state === AssessmentTimeService::STATE_LATE_SCANNING;
    }

    public function isEnded(): bool
    {
        return $this->state === AssessmentTimeService::STATE_ENDED;
    }

    public function isCompleted(): bool
    {
        return $this->state === AssessmentTimeService::STATE_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->state === AssessmentTimeService::STATE_CANCELLED;
    }

    public function isInvalidSchedule(): bool
    {
        return $this->state === AssessmentTimeService::STATE_INVALID_SCHEDULE;
    }

    public function __get(string $name)
    {
        return match ($name) {
            'is_upcoming'         => $this->isUpcoming(),
            'is_early_scanning'   => $this->isEarlyScanning(),
            'is_regular_scanning',
            'is_in_progress'      => $this->isInProgress(),
            'is_late_scanning'    => $this->isLateScanning(),
            'is_ended',
            'is_closed'           => $this->isEnded(),
            'is_completed'        => $this->isCompleted(),
            'is_cancelled'        => $this->isCancelled(),
            'is_invalid_schedule' => $this->isInvalidSchedule(),
            default               => null,
        };
    }

    public function __isset(string $name): bool
    {
        return in_array($name, [
            'is_upcoming', 'is_early_scanning', 'is_regular_scanning',
            'is_in_progress', 'is_late_scanning', 'is_ended', 'is_closed',
            'is_completed', 'is_cancelled', 'is_invalid_schedule'
        ], true);
    }

    public function canScan(): bool
    {
        return $this->can_scan;
    }

    public function canEnter(): bool
    {
        return $this->can_enter;
    }

    public function canDepart(): bool
    {
        return $this->can_depart;
    }

    public function canSubmit(): bool
    {
        return $this->can_submit;
    }

    public function toArray(): array
    {
        return [
            'state'                  => $this->state,
            'can_scan'               => $this->can_scan,
            'can_start_session'      => $this->can_start_session,
            'can_enter'              => $this->can_enter,
            'can_depart'             => $this->can_depart,
            'can_submit'             => $this->can_submit,
            'scan_classification'    => $this->scan_classification,
            'early_window_start'     => $this->early_window_start?->toIso8601String(),
            'start_time'             => $this->start_time?->toIso8601String(),
            'end_time'               => $this->end_time?->toIso8601String(),
            'late_window_end'        => $this->late_window_end?->toIso8601String(),
            'explanation'            => $this->explanation,
            'badge_label'            => $this->badge_label,
            'badge_class'            => $this->badge_class,
            'admin_action_required'  => $this->admin_action_required,
        ];
    }
}
