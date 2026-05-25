<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RiskIntelligenceService
{
    private string $jsonPath;
    private string $htmlPath;
    private ?array $cachedViewModel = null;

    public function __construct()
    {
        $this->jsonPath = storage_path('app/risk-analysis/risk_report.json');
        $this->htmlPath = storage_path('app/risk-analysis/risk_report.html');
    }

    public function viewModel(): array
    {
        if ($this->cachedViewModel !== null) {
            return $this->cachedViewModel;
        }

        $python = $this->loadPythonReport();
        $fallback = $this->liveLaravelSummary();

        if (($python['usable'] ?? false) === true && $this->pythonReportIsCurrent($python['model'], $fallback)) {
            return $this->cachedViewModel = $python['model'];
        }

        if (($python['error'] ?? null) !== null) {
            $fallback['notice'] = 'Enhanced analysis could not be read. Showing the current system summary instead.';
            $fallback['error'] = $python['error'];
        } elseif (($python['usable'] ?? false) === true) {
            $fallback['notice'] = 'Current activity is shown from live system records. Enhanced trend analysis will appear after it is refreshed.';
        }

        return $this->cachedViewModel = $fallback;
    }

    public function getIntelligenceViewModel(): array
    {
        return $this->viewModel();
    }

    public function dashboardSummary(): array
    {
        $model = $this->viewModel();

        return [
            'source' => $model['source'],
            'source_label' => $model['source_label'],
            'status' => $model['status'],
            'notice' => $model['notice'],
            'generated_at' => $model['generated_at'],
            'total_scans' => (int) ($model['summary']['total_scans'] ?? 0),
            'duplicate_count' => (int) ($model['summary']['duplicate_count'] ?? 0),
            'high_risk_count' => (int) (($model['risk_overview']['critical_risk_students_count'] ?? 0) + ($model['risk_overview']['high_risk_students_count'] ?? 0)),
        ];
    }

    public function getDashboardSummary(): array
    {
        return $this->dashboardSummary();
    }

    public function getWarningCounts(): array
    {
        $model = $this->viewModel();
        $students = collect($model['student_risks'] ?? []);
        $examiners = collect($model['suspicious_examiners'] ?? []);
        $activeReviewItems = $students->count() + $examiners->count();

        return [
            'students' => $students->count(),
            'examiners' => $examiners->count(),
            'risk' => $activeReviewItems,
            'critical_or_high' => $students
                ->filter(fn ($row) => in_array($row['risk_level'] ?? '', ['critical', 'high'], true))
                ->count(),
        ];
    }

    public function getStudentsNeedingReview(): array
    {
        return collect($this->viewModel()['student_risks'] ?? [])
            ->keyBy('matric_no')
            ->all();
    }

    public function getExaminersNeedingReview(): array
    {
        return collect($this->viewModel()['suspicious_examiners'] ?? [])
            ->keyBy(fn ($row) => (string) ($row['examiner_id'] ?? ''))
            ->all();
    }

    public function getStudentWarning(string|object $student): array
    {
        $matric = is_object($student) ? (string) ($student->matric_no ?? '') : (string) $student;
        $row = $this->getStudentsNeedingReview()[$matric] ?? null;

        return $this->warningShape($row, 'No warning activity found for this student.');
    }

    public function getExaminerWarning(string|int|object $examiner): array
    {
        $id = is_object($examiner) ? (string) ($examiner->examiner_id ?? '') : (string) $examiner;
        $row = $this->getExaminersNeedingReview()[$id] ?? null;

        return $this->warningShape($row, 'No suspicious examiner activity detected.');
    }

    private function pythonReportIsCurrent(array $pythonModel, array $liveModel): bool
    {
        $pythonTotal = (int) ($pythonModel['summary']['total_scans'] ?? 0);
        $liveTotal = (int) ($liveModel['summary']['total_scans'] ?? 0);

        if ($liveTotal === 0) {
            return true;
        }

        if ($pythonTotal < $liveTotal) {
            return false;
        }

        $latestScan = $this->latestScanTimestamp();
        $generatedAt = $pythonModel['generated_at'] ?? null;

        if (! $latestScan || ! $generatedAt) {
            return false;
        }

        try {
            return \Carbon\Carbon::parse($generatedAt)->greaterThanOrEqualTo(\Carbon\Carbon::parse($latestScan));
        } catch (\Throwable) {
            return false;
        }
    }

    private function loadPythonReport(): array
    {
        if (! file_exists($this->jsonPath)) {
            return ['usable' => false, 'error' => null];
        }

        $decoded = json_decode((string) file_get_contents($this->jsonPath), true);

        if (! is_array($decoded)) {
            return ['usable' => false, 'error' => 'Risk report JSON could not be parsed.'];
        }

        return [
            'usable' => true,
            'error' => null,
            'model' => $this->normalizePythonReport($decoded),
        ];
    }

    private function normalizePythonReport(array $data): array
    {
        $summarySource = is_array($data['summary'] ?? null) ? $data['summary'] : $data;
        $overviewSource = is_array($data['risk_overview'] ?? null) ? $data['risk_overview'] : [];
        $students = collect($data['student_risks'] ?? $data['high_risk_students'] ?? [])->map(fn ($row) => $this->studentRow((array) $row))->values()->all();
        $examiners = collect($data['suspicious_examiners'] ?? [])->map(fn ($row) => $this->examinerRow((array) $row))->values()->all();
        $devices = collect($data['suspicious_devices'] ?? [])->map(fn ($row) => $this->deviceRow((array) $row, 'device'))->values()->all();
        $ips = collect($data['suspicious_ips'] ?? [])->map(fn ($row) => $this->deviceRow((array) $row, 'ip'))->values()->all();
        $criticalRiskStudents = collect($students)->where('risk_level', 'critical')->count();
        $highRiskStudents = collect($students)->where('risk_level', 'high')->count();
        $mediumRiskStudents = collect($students)->where('risk_level', 'medium')->count();

        return [
            'source' => 'python',
            'source_label' => 'Enhanced Analysis',
            'status' => $highRiskStudents > 0 ? 'Review needed' : 'Monitoring',
            'notice' => null,
            'error' => null,
            'generated_at' => $data['generated_at'] ?? $summarySource['generated_at'] ?? null,
            'last_updated_label' => $this->formatTimestamp($data['generated_at'] ?? $summarySource['generated_at'] ?? null),
            'freshness_label' => 'Source: Enhanced analysis',
            'summary' => $this->summaryShape($summarySource),
            'risk_overview' => [
                'critical_risk_students_count' => (int) ($overviewSource['critical_risk_students_count'] ?? $criticalRiskStudents),
                'high_risk_students_count' => (int) ($overviewSource['high_risk_students_count'] ?? $highRiskStudents),
                'medium_risk_students_count' => (int) ($overviewSource['medium_risk_students_count'] ?? $mediumRiskStudents),
                'suspicious_examiners_count' => (int) ($overviewSource['suspicious_examiners_count'] ?? count($examiners)),
                'suspicious_devices_count' => (int) ($overviewSource['suspicious_devices_count'] ?? count($devices)),
                'suspicious_ips_count' => (int) ($overviewSource['suspicious_ips_count'] ?? count($ips)),
                'duplicate_attempts' => (int) ($summarySource['duplicate_count'] ?? 0),
                'rejected_attempts' => (int) ($summarySource['rejected_count'] ?? 0),
            ],
            'risk_distribution' => $data['risk_distribution'] ?? $data['risk_summary'] ?? ['low' => 0, 'medium' => $mediumRiskStudents, 'high' => $highRiskStudents, 'critical' => $criticalRiskStudents],
            'department_trends' => $this->trendRows($data['department_trends'] ?? []),
            'level_trends' => $this->trendRows($data['level_trends'] ?? []),
            'key_observations' => $this->nonEmptyList($data['key_observations'] ?? data_get($data, 'daily_summary.key_observations') ?? []),
            'student_risks' => $students,
            'high_risk_students' => $students,
            'suspicious_examiners' => $examiners,
            'suspicious_devices' => $devices,
            'suspicious_ips' => $ips,
            'recommendations' => $this->nonEmptyList($data['recommendations'] ?? data_get($data, 'daily_summary.recommendations') ?? []),
        ];
    }

    private function liveLaravelSummary(): array
    {
        $summary = $this->liveSummaryMetrics();
        $students = $this->fallbackStudentRisk();
        $examiners = $this->fallbackExaminerRisk();
        [$devices, $ips] = $this->fallbackDeviceIpRisk();
        $departmentTrends = $this->fallbackTrends('department');
        $levelTrends = $this->fallbackTrends('level');
        $observations = $this->fallbackObservations($summary, $students, $examiners, $devices, $ips);
        $recommendations = $this->fallbackRecommendations($summary, $students, $examiners, $devices, $ips);

        return [
            'source' => 'live',
            'source_label' => 'Live System Summary',
            'status' => 'Live summary available',
            'notice' => 'Current activity is shown from live system records. Enhanced trend analysis can be refreshed for deeper review.',
            'error' => null,
            'generated_at' => now()->toIso8601String(),
            'last_updated_label' => 'Generated live for this request',
            'freshness_label' => 'Source: Current system records',
            'summary' => $summary,
            'risk_overview' => [
                'critical_risk_students_count' => collect($students)->where('risk_level', 'critical')->count(),
                'high_risk_students_count' => collect($students)->where('risk_level', 'high')->count(),
                'medium_risk_students_count' => collect($students)->where('risk_level', 'medium')->count(),
                'suspicious_examiners_count' => count($examiners),
                'suspicious_devices_count' => count($devices),
                'suspicious_ips_count' => count($ips),
                'duplicate_attempts' => $summary['duplicate_count'],
                'rejected_attempts' => $summary['rejected_count'],
            ],
            'risk_distribution' => [
                'low' => collect($students)->where('risk_level', 'low')->count(),
                'medium' => collect($students)->where('risk_level', 'medium')->count(),
                'high' => collect($students)->where('risk_level', 'high')->count(),
                'critical' => collect($students)->where('risk_level', 'critical')->count(),
            ],
            'department_trends' => $departmentTrends,
            'level_trends' => $levelTrends,
            'key_observations' => $observations,
            'student_risks' => $students,
            'high_risk_students' => $students,
            'suspicious_examiners' => $examiners,
            'suspicious_devices' => $devices,
            'suspicious_ips' => $ips,
            'recommendations' => $recommendations,
        ];
    }

    private function liveSummaryMetrics(): array
    {
        $scanCounts = $this->hasTable('verification_logs')
            ? DB::table('verification_logs')->select('decision', DB::raw('COUNT(*) as total'))->groupBy('decision')->pluck('total', 'decision')
            : collect();

        $total = (int) $scanCounts->sum();
        $approved = (int) ($scanCounts['APPROVED'] ?? 0);
        $rejected = (int) ($scanCounts['REJECTED'] ?? 0);
        $duplicate = (int) ($scanCounts['DUPLICATE'] ?? 0);
        $unusedTokens = $this->hasTable('qr_tokens') && Schema::hasColumn('qr_tokens', 'status')
            ? DB::table('qr_tokens')->whereIn('status', ['UNUSED', 'ACTIVE'])->count()
            : 0;

        return [
            'total_scans' => $total,
            'approved_count' => $approved,
            'rejected_count' => $rejected,
            'duplicate_count' => $duplicate,
            'approval_rate' => $this->rate($approved, $total),
            'duplicate_rate' => $this->rate($duplicate, $total),
            'rejection_rate' => $this->rate($rejected, $total),
            'total_students' => $this->countTable('students'),
            'verified_payments' => $this->countTable('payment_records'),
            'qr_issued' => $this->countTable('qr_tokens'),
            'active_tokens' => $unusedTokens,
            'unused_tokens' => $unusedTokens,
        ];
    }

    private function fallbackStudentRisk(): array
    {
        if (! $this->hasTable('verification_logs') || ! $this->hasTable('qr_tokens')) {
            return [];
        }

        $query = DB::table('verification_logs')
            ->leftJoin('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id');
        $select = [
            'qr_tokens.student_id as matric_no',
            'verification_logs.decision',
            'verification_logs.token_id',
            'verification_logs.timestamp',
            'verification_logs.device_fp',
            'verification_logs.ip_address',
            'qr_tokens.status as token_status',
            DB::raw('NULL as student_name'),
            DB::raw('NULL as level'),
            DB::raw('NULL as department'),
        ];

        if ($this->hasTable('students')) {
            $query->leftJoin('students', 'qr_tokens.student_id', '=', 'students.matric_no');
            $select[7] = 'students.full_name as student_name';
            $select[8] = 'students.level';

            if ($this->hasTable('departments')) {
                $query->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id');
                $select[9] = 'departments.dept_name as department';
            }
        }

        $rows = $query->select($select)
            ->orderByDesc('verification_logs.timestamp')
            ->limit(1000)
            ->get()
            ->filter(fn ($row) => ! empty($row->matric_no));

        return $rows->groupBy('matric_no')->map(function (Collection $logs, string $matric) {
            $first = $logs->first();
            $rejected = $logs->where('decision', 'REJECTED')->count();
            $duplicate = $logs->where('decision', 'DUPLICATE')->count();
            $totalScans = $logs->count();
            $uniqueDevices = $logs->pluck('device_fp')->filter()->unique()->count();
            $uniqueIps = $logs->pluck('ip_address')->filter()->unique()->count();
            $repeatedTokens = $logs->groupBy('token_id')->filter(fn (Collection $tokenLogs) => $tokenLogs->count() > 1)->count();
            $hasCloseAttempts = $this->hasCloseAttempts($logs->pluck('timestamp')->filter()->all());
            $tokenStatuses = $logs->pluck('token_status')->filter()->map(fn ($status) => strtoupper((string) $status))->unique();
            $hasVerifiedPayment = $this->hasVerifiedPayment($matric);
            $score = 0;
            $reasons = [];

            if ($duplicate > 0 && $tokenStatuses->contains('USED')) {
                $score += 40;
                $reasons[] = 'This exam pass was scanned again after it had already been approved';
            }
            if ($duplicate >= 1) {
                $score += 35;
                $reasons[] = $duplicate . ' repeated scan attempt(s)';
            }
            if ($rejected >= 2) {
                $score += 25;
                $reasons[] = $rejected . ' rejected scan attempts';
            }
            if ($uniqueDevices >= 2) {
                $score += 20;
                $reasons[] = 'same student scanned from multiple devices';
            }
            if ($uniqueIps >= 2) {
                $score += 20;
                $reasons[] = 'unusual network activity for the same student';
            }
            if (! $hasVerifiedPayment) {
                $score += 20;
                $reasons[] = 'payment is missing or unverified';
            }
            $suspiciousStatuses = $tokenStatuses->reject(fn ($status) => in_array($status, ['UNUSED', 'USED'], true));
            if ($suspiciousStatuses->isNotEmpty()) {
                $score += 15;
                $reasons[] = 'exam pass status needs review';
            }
            if ($hasCloseAttempts) {
                $score += 15;
                $reasons[] = 'multiple scan attempts occurred within two minutes';
            }
            if ($totalScans >= 4) {
                $score += 10;
                $reasons[] = 'high scan attempt count for one exam access';
            }

            return $this->studentRow([
                'matric_no' => $matric,
                'student_name' => $first->student_name,
                'department' => $first->department,
                'level' => $first->level,
                'score' => $score,
                'risk_level' => $this->riskLevel($score),
                'reasons' => $reasons,
                'duplicate_count' => $duplicate,
                'rejected_count' => $rejected,
                'total_scans' => $totalScans,
                'last_activity' => optional($logs->sortByDesc('timestamp')->first())->timestamp,
                'recommendation' => $score > 0 ? 'Review this student scan history before clearing the record.' : 'No action required.',
            ]);
        })->filter(fn ($row) => ($row['score'] ?? 0) > 0)->sortByDesc('score')->take(15)->values()->all();
    }

    private function fallbackExaminerRisk(): array
    {
        if (! $this->hasTable('verification_logs')) {
            return [];
        }

        $query = DB::table('verification_logs');
        $select = [
            'verification_logs.examiner_id',
            'verification_logs.decision',
            'verification_logs.token_id',
            'verification_logs.timestamp',
            'verification_logs.device_fp',
            'verification_logs.ip_address',
            DB::raw('NULL as examiner_name'),
            DB::raw('NULL as matric_no'),
        ];

        if ($this->hasTable('examiners')) {
            $query->leftJoin('examiners', 'verification_logs.examiner_id', '=', 'examiners.examiner_id');
            $select[6] = 'examiners.full_name as examiner_name';
        }

        if ($this->hasTable('qr_tokens')) {
            $query->leftJoin('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id');
            $select[7] = 'qr_tokens.student_id as matric_no';
        }

        $rows = $query->select($select)->limit(1000)->get()
            ->filter(fn ($row) => ! empty($row->examiner_id));

        $average = max(1, (int) round($rows->count() / max(1, $rows->groupBy('examiner_id')->count())));

        return $rows->groupBy('examiner_id')->map(function (Collection $logs, string $examinerId) use ($average) {
            $first = $logs->first();
            $approved = $logs->where('decision', 'APPROVED')->count();
            $rejected = $logs->where('decision', 'REJECTED')->count();
            $duplicate = $logs->where('decision', 'DUPLICATE')->count();
            $repeatedTokens = $logs->groupBy('token_id')->filter(fn (Collection $tokenLogs) => $tokenLogs->count() > 1)->count();
            $uniqueDevices = $logs->pluck('device_fp')->filter()->unique()->count();
            $uniqueIps = $logs->pluck('ip_address')->filter()->unique()->count();
            $suspiciousStudents = $logs->whereIn('decision', ['DUPLICATE', 'REJECTED'])->pluck('matric_no')->filter()->unique()->count();
            $rapidActivity = $this->hasCloseAttempts($logs->pluck('timestamp')->filter()->all());
            $score = 0;
            $reasons = [];

            if ($duplicate >= 1) {
                $score += 30;
                $reasons[] = 'repeated scan attempts recorded';
            }
            if ($rejected >= 2) {
                $score += 25;
                $reasons[] = 'rejected scan count requires review';
            }
            if ($repeatedTokens >= 1) {
                $score += 20;
                $reasons[] = 'same exam pass scanned repeatedly';
            }
            if ($logs->count() >= 5 && $logs->count() > ($average * 1.5)) {
                $score += 20;
                $reasons[] = 'scan volume is high compared with peers';
            }
            if ($rapidActivity) {
                $score += 15;
                $reasons[] = 'scan attempts happened too rapidly';
            }
            if ($suspiciousStudents >= 2) {
                $score += 15;
                $reasons[] = 'linked to multiple suspicious student attempts';
            }
            if (($uniqueDevices + $uniqueIps) >= 4) {
                $score += 10;
                $reasons[] = 'scanner activity spans several device or network signals';
            }

            return $this->examinerRow([
                'examiner_id' => $examinerId,
                'examiner_name' => $first->examiner_name,
                'total_scans' => $logs->count(),
                'approved_count' => $approved,
                'rejected_count' => $rejected,
                'duplicate_count' => $duplicate,
                'suspicious_score' => $score,
                'risk_level' => $this->riskLevel($score),
                'reasons' => $reasons,
                'suspicious_students_count' => $suspiciousStudents,
                'last_activity' => optional($logs->sortByDesc('timestamp')->first())->timestamp,
                'recommendation' => $score > 0 ? 'Confirm whether the repeated scans were intentional.' : 'No action required.',
            ]);
        })->filter(fn ($row) => ($row['suspicious_score'] ?? 0) > 0)->sortByDesc('suspicious_score')->take(15)->values()->all();
    }

    private function fallbackDeviceIpRisk(): array
    {
        if (! $this->hasTable('verification_logs')) {
            return [[], []];
        }

        $select = ['verification_logs.decision', 'verification_logs.examiner_id'];
        $hasDevice = Schema::hasColumn('verification_logs', 'device_fp');
        $hasIp = Schema::hasColumn('verification_logs', 'ip_address');

        if ($hasDevice) {
            $select[] = 'verification_logs.device_fp';
        }
        if ($hasIp) {
            $select[] = 'verification_logs.ip_address';
        }
        if ($this->hasTable('qr_tokens')) {
            $select[] = 'qr_tokens.student_id as matric_no';
            $query = DB::table('verification_logs')->leftJoin('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id');
        } else {
            $query = DB::table('verification_logs');
        }

        $rows = $query->select($select)->limit(1000)->get();

        return [
            $hasDevice ? $this->identifierRisk($rows, 'device_fp', 'device') : [],
            $hasIp ? $this->identifierRisk($rows, 'ip_address', 'ip') : [],
        ];
    }

    private function fallbackTrends(string $type): array
    {
        if (! $this->hasTable('verification_logs') || ! $this->hasTable('qr_tokens') || ! $this->hasTable('students')) {
            return [];
        }

        $query = DB::table('verification_logs')
            ->leftJoin('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->leftJoin('students', 'qr_tokens.student_id', '=', 'students.matric_no');

        if ($type === 'department' && $this->hasTable('departments')) {
            $query->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id');
            $label = 'departments.dept_name';
        } else {
            $label = 'students.level';
        }

        $rows = $query
            ->select(DB::raw("COALESCE($label, 'Unknown') as label"), 'verification_logs.decision')
            ->limit(1000)
            ->get()
            ->groupBy('label');

        return $rows->map(function (Collection $logs, string $label) {
            $total = $logs->count();
            $approved = $logs->where('decision', 'APPROVED')->count();
            $rejected = $logs->where('decision', 'REJECTED')->count();
            $duplicate = $logs->where('decision', 'DUPLICATE')->count();

            return [
                'label' => $label,
                'total_scans' => $total,
                'approved_count' => $approved,
                'rejected_count' => $rejected,
                'duplicate_count' => $duplicate,
                'approval_rate' => $this->rate($approved, $total),
                'duplicate_rate' => $this->rate($duplicate, $total),
                'rejection_rate' => $this->rate($rejected, $total),
                'risk_score' => ($duplicate * 20) + ($rejected * 15),
            ];
        })->sortByDesc('risk_score')->values()->all();
    }

    private function identifierRisk(Collection $rows, string $field, string $type): array
    {
        return $rows->filter(fn ($row) => ! empty($row->{$field}))
            ->groupBy($field)
            ->map(function (Collection $logs, string|int|null $identifier = null) use ($field, $type) {
                $identifier = (string) ($identifier ?? $logs->first()?->{$field} ?? '-');
                $students = $logs->pluck('matric_no')->filter()->unique()->count();
                $examiners = $logs->pluck('examiner_id')->filter()->unique()->count();
                $rejected = $logs->where('decision', 'REJECTED')->count();
                $duplicate = $logs->where('decision', 'DUPLICATE')->count();
                $score = 0;
                $reasons = [];

                if ($students >= 3) {
                    $score += 30;
                    $reasons[] = 'scanner device appears across many students';
                }
                if ($rejected >= 2) {
                    $score += 25;
                    $reasons[] = 'many rejected scans from this scanner signal';
                }
                if ($duplicate >= 1) {
                    $score += 25;
                    $reasons[] = 'many repeated scans from this scanner signal';
                }
                if ($examiners >= 2) {
                    $score += 15;
                    $reasons[] = 'scanner signal linked to multiple examiners';
                }

                return $this->deviceRow([
                    'identifier' => $identifier,
                    'type' => $type,
                    'total_scans' => $logs->count(),
                    'unique_students' => $students,
                    'unique_examiners' => $examiners,
                    'rejected_count' => $rejected,
                    'duplicate_count' => $duplicate,
                    'risk_level' => $this->riskLevel($score),
                    'reasons' => $reasons,
                    'recommendation' => $score > 0 ? 'Review scanner assignment and repeated activity patterns.' : 'No action required.',
                ], $type);
            })->filter(fn ($row) => ! empty($row['reasons']))->sortByDesc('total_scans')->take(15)->values()->all();
    }

    private function fallbackObservations(array $summary, array $students, array $examiners, array $devices, array $ips): array
    {
        $items = ['Current scan activity is being shown from live system records.'];

        if ($summary['total_scans'] === 0) {
            $items[] = 'No verification scan activity has been recorded yet.';
        }
        if ($summary['duplicate_count'] > 0) {
            $items[] = $summary['duplicate_count'] . ' repeated scan attempt(s) detected.';
            $items[] = 'Repeated verification activity is visible in the live scan logs.';
        }
        if ($summary['rejected_count'] > 0) {
            $items[] = $summary['rejected_count'] . ' rejected scan attempt(s) detected.';
        }
        $items[] = count($students) > 0 ? count($students) . ' student risk record(s) require review.' : 'No high-risk student activity detected from current records.';
        if (count($examiners) > 0) {
            $items[] = 'One or more examiners have elevated rejected or repeated scan activity.';
        }
        if ((count($devices) + count($ips)) > 0) {
            $items[] = 'Scanner device or network patterns are available for review.';
        }

        return $items;
    }

    private function fallbackRecommendations(array $summary, array $students, array $examiners, array $devices, array $ips): array
    {
        $items = [
            'Use enhanced risk analysis during active exam periods for deeper scanner and student pattern review.',
            'Keep demo mode disabled for real production usage.',
        ];

        if ($summary['duplicate_count'] > 0) {
            $items[] = 'Review repeated scan attempts before closing the exam session.';
        }
        if (count($examiners) > 0) {
            $items[] = 'Confirm suspicious examiner activity if rejected scans are unusually high.';
        }
        if (count($students) > 0) {
            $items[] = 'Review flagged student payment, exam pass, and scan history.';
        }
        if ((count($devices) + count($ips)) > 0) {
            $items[] = 'Check whether repeated scanner or network patterns match expected assignments.';
        }

        return $items;
    }

    private function summaryShape(array $source): array
    {
        $total = (int) ($source['total_scans'] ?? 0);
        $approved = (int) ($source['approved_count'] ?? 0);
        $rejected = (int) ($source['rejected_count'] ?? 0);
        $duplicate = (int) ($source['duplicate_count'] ?? 0);

        return [
            'total_scans' => $total,
            'approved_count' => $approved,
            'rejected_count' => $rejected,
            'duplicate_count' => $duplicate,
            'approval_rate' => (float) ($source['approval_rate'] ?? $this->rate($approved, $total)),
            'duplicate_rate' => (float) ($source['duplicate_rate'] ?? $this->rate($duplicate, $total)),
            'rejection_rate' => (float) ($source['rejection_rate'] ?? $this->rate($rejected, $total)),
            'total_students' => (int) ($source['total_students'] ?? $this->countTable('students')),
            'verified_payments' => (int) ($source['verified_payments'] ?? $this->countTable('payment_records')),
            'qr_issued' => (int) ($source['qr_issued'] ?? $source['active_tokens'] ?? $source['unused_tokens'] ?? 0),
            'active_tokens' => (int) ($source['active_tokens'] ?? 0),
            'unused_tokens' => (int) ($source['unused_tokens'] ?? 0),
        ];
    }

    private function warningShape(?array $row, string $clearMessage): array
    {
        if (! $row) {
            return [
                'has_warning' => false,
                'label' => 'No warning activity',
                'level' => 'low',
                'message' => $clearMessage,
                'reasons' => [],
                'recommendation' => 'No action required.',
                'duplicate_count' => 0,
                'rejected_count' => 0,
                'total_scans' => 0,
                'last_activity' => '',
                'students_affected' => 0,
            ];
        }

        $level = strtolower((string) ($row['risk_level'] ?? 'medium'));

        return [
            'has_warning' => true,
            'label' => match ($level) {
                'critical' => 'Critical review',
                'high' => 'High risk',
                'medium' => 'Needs review',
                default => 'Review',
            },
            'level' => $level,
            'message' => $this->nonEmptyList($row['reasons'] ?? [])[0] ?? 'Repeated scan activity needs review.',
            'reasons' => $this->nonEmptyList($row['reasons'] ?? []),
            'recommendation' => (string) ($row['recommendation'] ?? 'Review the scan history and confirm whether repeated scans were intentional.'),
            'duplicate_count' => (int) ($row['duplicate_count'] ?? 0),
            'rejected_count' => (int) ($row['rejected_count'] ?? 0),
            'total_scans' => (int) ($row['total_scans'] ?? 0),
            'last_activity' => (string) ($row['last_activity'] ?? ''),
            'students_affected' => (int) ($row['suspicious_students_count'] ?? 0),
            'score' => (int) ($row['score'] ?? $row['suspicious_score'] ?? 0),
        ];
    }

    private function studentRow(array $row): array
    {
        $score = (int) ($row['score'] ?? 0);

        return [
            'matric_no' => (string) ($row['matric_no'] ?? '-'),
            'student_name' => (string) ($row['student_name'] ?? '-'),
            'department' => (string) ($row['department'] ?? '-'),
            'level' => (string) ($row['level'] ?? '-'),
            'score' => $score,
            'risk_level' => strtolower((string) ($row['risk_level'] ?? $this->riskLevel($score))),
            'reasons' => $this->nonEmptyList($row['reasons'] ?? []),
            'duplicate_count' => (int) ($row['duplicate_count'] ?? 0),
            'rejected_count' => (int) ($row['rejected_count'] ?? 0),
            'total_scans' => (int) ($row['total_scans'] ?? 0),
            'last_activity' => (string) ($row['last_activity'] ?? ''),
            'recommendation' => $this->displayText((string) ($row['recommendation'] ?? 'Review this student activity.')),
        ];
    }

    private function examinerRow(array $row): array
    {
        $score = (int) ($row['suspicious_score'] ?? 0);

        return [
            'examiner_id' => (string) ($row['examiner_id'] ?? '-'),
            'examiner_name' => (string) ($row['examiner_name'] ?? '-'),
            'total_scans' => (int) ($row['total_scans'] ?? 0),
            'approved_count' => (int) ($row['approved_count'] ?? 0),
            'rejected_count' => (int) ($row['rejected_count'] ?? 0),
            'duplicate_count' => (int) ($row['duplicate_count'] ?? 0),
            'suspicious_score' => $score,
            'risk_level' => strtolower((string) ($row['risk_level'] ?? $this->riskLevel($score))),
            'reasons' => $this->nonEmptyList($row['reasons'] ?? []),
            'suspicious_students_count' => (int) ($row['suspicious_students_count'] ?? 0),
            'last_activity' => (string) ($row['last_activity'] ?? ''),
            'recommendation' => $this->displayText((string) ($row['recommendation'] ?? 'Review examiner activity log.')),
        ];
    }

    private function deviceRow(array $row, string $defaultType): array
    {
        return [
            'identifier' => (string) ($row['identifier'] ?? '-'),
            'type' => (string) ($row['type'] ?? $defaultType),
            'total_scans' => (int) ($row['total_scans'] ?? 0),
            'unique_students' => (int) ($row['unique_students'] ?? 0),
            'unique_examiners' => (int) ($row['unique_examiners'] ?? 0),
            'rejected_count' => (int) ($row['rejected_count'] ?? 0),
            'duplicate_count' => (int) ($row['duplicate_count'] ?? 0),
            'risk_level' => strtolower((string) ($row['risk_level'] ?? 'low')),
            'reasons' => $this->nonEmptyList($row['reasons'] ?? []),
            'recommendation' => $this->displayText((string) ($row['recommendation'] ?? 'Review scanner context.')),
        ];
    }

    private function trendRows(array $rows): array
    {
        return collect($rows)->map(fn ($row) => [
            'label' => (string) (($row['label'] ?? $row['name'] ?? 'Unknown')),
            'total_scans' => (int) ($row['total_scans'] ?? 0),
            'approved_count' => (int) ($row['approved_count'] ?? 0),
            'rejected_count' => (int) ($row['rejected_count'] ?? 0),
            'duplicate_count' => (int) ($row['duplicate_count'] ?? 0),
            'approval_rate' => (float) ($row['approval_rate'] ?? 0),
            'duplicate_rate' => (float) ($row['duplicate_rate'] ?? 0),
            'rejection_rate' => (float) ($row['rejection_rate'] ?? 0),
            'risk_score' => (int) ($row['risk_score'] ?? 0),
        ])->values()->all();
    }

    private function nonEmptyList(mixed $value): array
    {
        return collect((array) $value)
            ->filter(fn ($item) => trim((string) $item) !== '')
            ->values()
            ->map(fn ($item) => $this->displayText((string) $item))
            ->all();
    }

    private function displayText(string $text): string
    {
        $text = str_ireplace([
            'QR token was scanned again after approval',
            'same student/token scanned repeatedly',
            'same token scanned repeatedly',
            'token was scanned again after approval',
            'duplicate/repeated scan attempt(s)',
            'duplicate scan attempts',
            'duplicate scans',
            'Duplicate scans',
            'duplicate scan',
            'Duplicate scan',
            'duplicate attempts',
            'Duplicate attempts',
            'duplicate device/IP patterns',
            'device/IP patterns',
            'source IP',
            'IP context',
            'Device ',
            'token status',
            'Token status',
            'token',
            'Token',
            'QR payload',
            'payload',
            'HMAC',
            'AES',
            'JSON',
            'storage/app/risk-analysis',
            'php artisan',
        ], [
            'This exam pass was scanned again after it had already been approved',
            'same student or exam pass scanned repeatedly',
            'same exam pass scanned repeatedly',
            'This exam pass was scanned again after it had already been approved',
            'repeated scan attempt(s)',
            'repeated scan attempts',
            'repeated scans',
            'Repeated scans',
            'repeated scan',
            'Repeated scan',
            'repeated attempts',
            'Repeated attempts',
            'scanner and network patterns',
            'scanner and network patterns',
            'network',
            'network context',
            'Scanner device ',
            'exam pass status',
            'Exam pass status',
            'exam pass',
            'Exam pass',
            'QR code',
            'scan information',
            'secure check',
            'secure check',
            'report',
            'risk analysis records',
            'admin command',
        ], $text);

        $text = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', 'network pattern', $text) ?? $text;
        $text = preg_replace('/\b[a-f0-9]{12,}\b/i', 'scanner device pattern', $text) ?? $text;
        $text = str_replace('Scanner device scanner device pattern', 'A scanner device pattern', $text);
        $text = str_replace('IP network pattern', 'A network pattern', $text);
        $text = str_replace('scanner Scanner device', 'scanner device', $text);
        $text = str_replace('Scanner Scanner device', 'Scanner device', $text);
        $text = str_replace('scanner-Scanner device', 'scanner-device', $text);
        $text = str_replace('shared-Scanner device', 'shared-device', $text);
        $text = str_replace('network/network context', 'network context', $text);

        return trim($text);
    }

    private function riskLevel(int $score): string
    {
        if ($score >= 75) {
            return 'critical';
        }

        if ($score >= 50) {
            return 'high';
        }

        return $score >= 25 ? 'medium' : 'low';
    }

    private function hasVerifiedPayment(string $matric): bool
    {
        if (! $this->hasTable('payment_records')) {
            return false;
        }

        return DB::table('payment_records')
            ->where('student_id', $matric)
            ->whereNotNull('verified_at')
            ->exists();
    }

    private function hasCloseAttempts(array $timestamps): bool
    {
        $times = collect($timestamps)
            ->map(function ($timestamp) {
                try {
                    return \Carbon\Carbon::parse($timestamp)->timestamp;
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter()
            ->sort()
            ->values();

        for ($i = 1; $i < $times->count(); $i++) {
            if (($times[$i] - $times[$i - 1]) <= 120) {
                return true;
            }
        }

        return false;
    }

    private function latestScanTimestamp(): ?string
    {
        if (! $this->hasTable('verification_logs')) {
            return null;
        }

        return DB::table('verification_logs')->max('timestamp');
    }

    private function rate(int $count, int $total): float
    {
        return $total > 0 ? round(($count / $total) * 100, 1) : 0.0;
    }

    private function countTable(string $table): int
    {
        return $this->hasTable($table) ? DB::table($table)->count() : 0;
    }

    private function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function formatTimestamp(?string $timestamp): string
    {
        if (! $timestamp) {
            return 'Updated recently';
        }

        try {
            return \Carbon\Carbon::parse($timestamp)->format('M j, Y g:i A');
        } catch (\Throwable) {
            return 'Updated recently';
        }
    }
}
