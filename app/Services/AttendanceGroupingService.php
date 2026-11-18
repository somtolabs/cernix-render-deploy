<?php

namespace App\Services;

use App\Support\MatricNumber;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceGroupingService
{
    public function __construct(
        protected MatriculationNumberService $matricService
    ) {}

    /**
     * Build the Department -> Level -> Students attendance hierarchy.
     *
     * @param int $sessionId
     * @param int|null $timetableId
     * @param array{
     *     department?: string|null,
     *     level?: string|null,
     *     status?: string|null,
     *     type?: string|null,
     *     q?: string|null
     * } $filters
     * @return array<string, mixed>
     */
    public function getGroupedAttendance(int $sessionId, ?int $timetableId = null, array $filters = []): array
    {
        $timetable = null;
        if ($timetableId && Schema::hasTable('timetables')) {
            $timetable = DB::table('timetables')
                ->leftJoin('departments', 'timetables.department_id', '=', 'departments.dept_id')
                ->where('timetables.id', $timetableId)
                ->select('timetables.*', 'departments.dept_name')
                ->first();
        }

        // 1. Gather all candidates and attendance records
        $studentMap = []; // keyed by normalized matric
        $rosterMatricSet = [];

        // If specific timetable is selected, load confirmed roster candidates
        if ($timetableId && Schema::hasTable('timetable_students')) {
            $rosterRows = DB::table('timetable_students')
                ->where('timetable_id', $timetableId)
                ->get();

            foreach ($rosterRows as $rRow) {
                $norm = $this->matricService->normalize((string) $rRow->matric_no);
                if ($norm === '') continue;

                $rosterMatricSet[$norm] = [
                    'candidate_type' => $rRow->candidate_type ?? 'regular',
                    'enrolled_by'    => $rRow->enrolled_by ?? null,
                    'enrolled_at'    => $rRow->created_at ?? null,
                ];

                $studentMap[$norm] = [
                    'matric_no'           => $norm,
                    'is_on_roster'        => true,
                    'candidate_type'      => $rRow->candidate_type ?? 'regular',
                    'attendance_id'       => null,
                    'status'              => 'not_checked_in', // default for roster candidate
                    'status_label'        => 'Yet to Appear',
                    'checked_in_at'       => null,
                    'submitted_at'        => null,
                    'checkin_method'      => null,
                    'assisted_reason'     => null,
                    'assisted_notes'      => null,
                    'entry_examiner_name' => null,
                    'exit_examiner_name'  => null,
                    'course_code'         => $timetable->course_code ?? null,
                    'course_title'        => $timetable->course_title ?? null,
                    'exam_date'           => $timetable->exam_date ?? null,
                    'start_time'          => $timetable->start_time ?? null,
                    'venue'               => $timetable->venue ?? null,
                    'scans'               => [],
                    'attendances'         => [],
                ];
            }
        }

        // Load attendance records
        $attQuery = DB::table('attendance_records')
            ->leftJoin('examiners as entry_ex', 'attendance_records.entry_examiner_id', '=', 'entry_ex.examiner_id')
            ->leftJoin('examiners as exit_ex', 'attendance_records.exit_examiner_id', '=', 'exit_ex.examiner_id')
            ->leftJoin('timetables', 'attendance_records.timetable_id', '=', 'timetables.id')
            ->leftJoin('departments', 'timetables.department_id', '=', 'departments.dept_id');

        if ($timetableId) {
            $attQuery->where('attendance_records.timetable_id', $timetableId);
            if ($sessionId) {
                $attQuery->where('attendance_records.session_id', $sessionId);
            }
        } elseif ($sessionId) {
            $attQuery->where('attendance_records.session_id', $sessionId);
        }

        if (!empty($filters['type'])) {
            $attQuery->where('timetables.assessment_type', $filters['type']);
        }

        $attRows = $attQuery->select(
            'attendance_records.*',
            'entry_ex.full_name as entry_examiner_name',
            'exit_ex.full_name as exit_examiner_name',
            'timetables.course_code',
            'timetables.course_title',
            'timetables.exam_date',
            'timetables.start_time',
            'timetables.venue',
            'timetables.assessment_type',
            'departments.dept_name'
        )->orderBy('attendance_records.checked_in_at')->get();

        foreach ($attRows as $row) {
            $norm = $this->matricService->normalize((string) $row->matric_no);
            if ($norm === '') continue;

            $statusKey = match($row->status) {
                'submitted'  => 'submitted',
                'flagged'    => 'flagged',
                'checked_in' => 'checked_in',
                default      => 'checked_in',
            };

            $statusLabel = match($statusKey) {
                'submitted'  => 'Submitted',
                'flagged'    => 'Flagged',
                'checked_in' => 'Checked In',
                default      => 'Checked In',
            };

            $attItem = [
                'id'                  => $row->id,
                'timetable_id'        => $row->timetable_id,
                'course_code'         => $row->course_code,
                'course_title'        => $row->course_title,
                'exam_date'           => $row->exam_date,
                'start_time'          => $row->start_time,
                'venue'               => $row->venue,
                'assessment_type'     => $row->assessment_type,
                'status'              => $statusKey,
                'status_label'        => $statusLabel,
                'checked_in_at'       => $row->checked_in_at,
                'submitted_at'        => $row->submitted_at,
                'checkin_method'      => $row->checkin_method,
                'assisted_reason'     => $row->assisted_reason,
                'assisted_notes'      => $row->assisted_notes,
                'entry_examiner_name' => $row->entry_examiner_name,
                'exit_examiner_name'  => $row->exit_examiner_name,
            ];

            if (!isset($studentMap[$norm])) {
                $isOnRoster = isset($rosterMatricSet[$norm]);
                $candidateType = $isOnRoster ? ($rosterMatricSet[$norm]['candidate_type'] ?? 'regular') : ($timetableId ? 'walk_in' : 'regular');

                $studentMap[$norm] = [
                    'matric_no'           => $norm,
                    'is_on_roster'        => $isOnRoster,
                    'candidate_type'      => $candidateType,
                    'attendance_id'       => $row->id,
                    'status'              => $statusKey,
                    'status_label'        => $statusLabel,
                    'checked_in_at'       => $row->checked_in_at,
                    'submitted_at'        => $row->submitted_at,
                    'checkin_method'      => $row->checkin_method,
                    'assisted_reason'     => $row->assisted_reason,
                    'assisted_notes'      => $row->assisted_notes,
                    'entry_examiner_name' => $row->entry_examiner_name,
                    'exit_examiner_name'  => $row->exit_examiner_name,
                    'course_code'         => $row->course_code ?? ($timetable->course_code ?? null),
                    'course_title'        => $row->course_title ?? ($timetable->course_title ?? null),
                    'exam_date'           => $row->exam_date ?? ($timetable->exam_date ?? null),
                    'start_time'          => $row->start_time ?? ($timetable->start_time ?? null),
                    'venue'               => $row->venue ?? ($timetable->venue ?? null),
                    'scans'               => [],
                    'attendances'         => [$attItem],
                ];
            } else {
                // Update student with attendance record
                $studentMap[$norm]['attendance_id']       = $row->id;
                $studentMap[$norm]['status']              = $statusKey;
                $studentMap[$norm]['status_label']        = $statusLabel;
                $studentMap[$norm]['checked_in_at']       = $row->checked_in_at;
                $studentMap[$norm]['submitted_at']        = $row->submitted_at;
                $studentMap[$norm]['checkin_method']      = $row->checkin_method;
                $studentMap[$norm]['assisted_reason']     = $row->assisted_reason;
                $studentMap[$norm]['assisted_notes']      = $row->assisted_notes;
                $studentMap[$norm]['entry_examiner_name'] = $row->entry_examiner_name;
                $studentMap[$norm]['exit_examiner_name']  = $row->exit_examiner_name;
                $studentMap[$norm]['attendances'][]       = $attItem;
            }
        }

        // 2. Load verification scans for these candidates
        $allMatrics = array_keys($studentMap);
        if (!empty($allMatrics) && Schema::hasTable('verification_logs')) {
            $scanQuery = DB::table('verification_logs')
                ->leftJoin('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
                ->leftJoin('examiners', 'verification_logs.examiner_id', '=', 'examiners.examiner_id')
                ->whereIn('qr_tokens.student_id', $allMatrics);

            if ($timetableId) {
                $scanQuery->where(function ($q) use ($timetableId) {
                    $q->where('verification_logs.timetable_id', $timetableId)
                      ->orWhere('qr_tokens.timetable_id', $timetableId);
                });
            }

            $scans = $scanQuery->select(
                'verification_logs.*',
                'qr_tokens.student_id as matric_no',
                'examiners.full_name as examiner_name',
                'examiners.username as examiner_username'
            )->orderBy('verification_logs.timestamp')->get();

            foreach ($scans as $scan) {
                $norm = $this->matricService->normalize((string) $scan->matric_no);
                if (isset($studentMap[$norm])) {
                    $studentMap[$norm]['scans'][] = [
                        'log_id'              => $scan->log_id,
                        'decision'            => $scan->decision,
                        'timestamp'           => $scan->timestamp,
                        'scan_classification' => $scan->scan_classification ?? 'regular',
                        'rejection_reason'    => $scan->rejection_reason,
                        'examiner_name'       => $scan->examiner_name ?? $scan->examiner_username ?? 'Examiner',
                        'device_fp'           => $scan->device_fp ?? null,
                        'ip_address'          => $scan->ip_address ?? null,
                    ];
                }
            }
        }

        // 3. Resolve student identities against Official Student Registry and Portal accounts
        $registryMap = [];
        if (!empty($allMatrics) && Schema::hasTable('official_students')) {
            $officialRows = DB::table('official_students')
                ->whereIn('matric_number', $allMatrics)
                ->orWhereIn('normalized_matric_number', $allMatrics)
                ->get();

            foreach ($officialRows as $off) {
                $n1 = $this->matricService->normalize((string) $off->matric_number);
                $n2 = $off->normalized_matric_number ? $this->matricService->normalize((string) $off->normalized_matric_number) : null;
                if ($n1) $registryMap[$n1] = $off;
                if ($n2) $registryMap[$n2] = $off;
            }
        }

        $portalMap = [];
        if (!empty($allMatrics) && Schema::hasTable('students')) {
            $portalRows = DB::table('students')
                ->whereIn('matric_no', $allMatrics)
                ->get();

            foreach ($portalRows as $port) {
                $n = $this->matricService->normalize((string) $port->matric_no);
                if ($n) $portalMap[$n] = $port;
            }
        }

        // 4. Enrich students with authoritative institutional info
        $enrichedStudents = [];
        foreach ($studentMap as $norm => $student) {
            $official = $registryMap[$norm] ?? null;
            $portal   = $portalMap[$norm] ?? null;

            if ($official) {
                $deptName = trim((string) ($official->department ?? '')) ?: 'Unassigned Department';
                $rawLevel = trim((string) ($official->level ?? ''));
                $cleanLevelNum = preg_replace('/[^0-9]/', '', $rawLevel);
                $levelKey = $cleanLevelNum !== '' ? ($cleanLevelNum . 'L') : 'UNASSIGNED';
                $levelLabel = $cleanLevelNum !== '' ? ($cleanLevelNum . ' Level') : 'Unassigned Level';

                $student['full_name']            = $official->full_name ?? ($portal->full_name ?? 'Official Student');
                $student['department']           = $deptName;
                $student['department_code']      = $official->department_code ?? null;
                $student['faculty']              = $official->faculty ?? null;
                $student['level']                = $rawLevel ?: null;
                $student['level_key']            = $levelKey;
                $student['level_label']          = $levelLabel;
                $student['is_registry_verified'] = true;
                $student['registry_status']      = $official->status ?? 'active';
            } else {
                $student['full_name']            = $portal->full_name ?? "Unregistered Student ({$norm})";
                $student['department']           = 'Unmapped / External Registry';
                $student['department_code']      = null;
                $student['faculty']              = null;
                $student['level']                = null;
                $student['level_key']            = 'UNASSIGNED';
                $student['level_label']          = 'Unassigned Level';
                $student['is_registry_verified'] = false;
                $student['registry_status']      = 'unmapped';
            }

            // Photo from portal account if available
            $student['profile_photo_path'] = $portal->profile_photo_path ?? $portal->photo_path ?? null;

            $enrichedStudents[] = $student;
        }

        // 5. Apply filters
        $filtered = collect($enrichedStudents)->filter(function ($st) use ($filters) {
            // Search filter: matric or full name
            if (!empty($filters['q'])) {
                $q = strtolower(trim((string) $filters['q']));
                $nameMatch = str_contains(strtolower($st['full_name'] ?? ''), $q);
                $matricMatch = str_contains(strtolower($st['matric_no'] ?? ''), $q);
                if (!$nameMatch && !$matricMatch) {
                    return false;
                }
            }

            // Department filter
            if (!empty($filters['department'])) {
                $targetDept = strtolower(trim((string) $filters['department']));
                if (strtolower(trim($st['department'] ?? '')) !== $targetDept) {
                    return false;
                }
            }

            // Level filter (e.g. '100', '200', '300', '400', '500', 'unassigned')
            if (!empty($filters['level'])) {
                $targetLevel = strtolower(trim((string) $filters['level']));
                $targetLevelDigits = preg_replace('/[^0-9]/', '', $targetLevel);
                if ($targetLevel === 'unassigned' || $targetLevel === 'unassigned level') {
                    if ($st['level_key'] !== 'UNASSIGNED') {
                        return false;
                    }
                } else {
                    $stLevelDigits = preg_replace('/[^0-9]/', '', (string) ($st['level'] ?? ''));
                    if ($stLevelDigits !== $targetLevelDigits) {
                        return false;
                    }
                }
            }

            // Status filter: 'checked_in', 'submitted', 'flagged', 'yet_to_appear' / 'not_checked_in'
            if (!empty($filters['status'])) {
                $targetStatus = strtolower(trim((string) $filters['status']));
                if ($targetStatus === 'yet_to_appear' || $targetStatus === 'not_checked_in' || $targetStatus === 'absent') {
                    if ($st['status'] !== 'not_checked_in') {
                        return false;
                    }
                } else {
                    if ($st['status'] !== $targetStatus) {
                        return false;
                    }
                }
            }

            return true;
        })->values();

        // 6. Assemble into institutional hierarchy: Department -> Level -> Students
        $hierarchy = [];
        $overallSummary = [
            'total_candidates' => 0,
            'total_present'    => 0,
            'checked_in'       => 0,
            'submitted'        => 0,
            'flagged'          => 0,
            'yet_to_appear'    => 0,
            'submit_rate'      => 0,
        ];

        foreach ($filtered as $st) {
            $deptName = $st['department'];
            $levelKey = $st['level_key'];
            $levelLabel = $st['level_label'];

            if (!isset($hierarchy[$deptName])) {
                $hierarchy[$deptName] = [
                    'department_name' => $deptName,
                    'department_code' => $st['department_code'] ?? null,
                    'faculty'         => $st['faculty'] ?? null,
                    'summary'         => [
                        'total_candidates' => 0,
                        'total_present'    => 0,
                        'checked_in'       => 0,
                        'submitted'        => 0,
                        'flagged'          => 0,
                        'yet_to_appear'    => 0,
                    ],
                    'levels' => [],
                ];
            }

            if (!isset($hierarchy[$deptName]['levels'][$levelKey])) {
                $hierarchy[$deptName]['levels'][$levelKey] = [
                    'level_key'   => $levelKey,
                    'level_label' => $levelLabel,
                    'raw_level'   => $st['level'],
                    'summary'     => [
                        'total_candidates' => 0,
                        'total_present'    => 0,
                        'checked_in'       => 0,
                        'submitted'        => 0,
                        'flagged'          => 0,
                        'yet_to_appear'    => 0,
                    ],
                    'students'    => [],
                ];
            }

            $hierarchy[$deptName]['levels'][$levelKey]['students'][] = $st;

            // Increment stats
            $isNotCheckedIn = ($st['status'] === 'not_checked_in');
            $isCheckedIn    = ($st['status'] === 'checked_in');
            $isSubmitted    = ($st['status'] === 'submitted');
            $isFlagged      = ($st['status'] === 'flagged');
            $isPresent      = !$isNotCheckedIn;

            // Level summary
            $hierarchy[$deptName]['levels'][$levelKey]['summary']['total_candidates']++;
            if ($isPresent)      $hierarchy[$deptName]['levels'][$levelKey]['summary']['total_present']++;
            if ($isCheckedIn)    $hierarchy[$deptName]['levels'][$levelKey]['summary']['checked_in']++;
            if ($isSubmitted)    $hierarchy[$deptName]['levels'][$levelKey]['summary']['submitted']++;
            if ($isFlagged)      $hierarchy[$deptName]['levels'][$levelKey]['summary']['flagged']++;
            if ($isNotCheckedIn) $hierarchy[$deptName]['levels'][$levelKey]['summary']['yet_to_appear']++;

            // Department summary
            $hierarchy[$deptName]['summary']['total_candidates']++;
            if ($isPresent)      $hierarchy[$deptName]['summary']['total_present']++;
            if ($isCheckedIn)    $hierarchy[$deptName]['summary']['checked_in']++;
            if ($isSubmitted)    $hierarchy[$deptName]['summary']['submitted']++;
            if ($isFlagged)      $hierarchy[$deptName]['summary']['flagged']++;
            if ($isNotCheckedIn) $hierarchy[$deptName]['summary']['yet_to_appear']++;

            // Overall summary
            $overallSummary['total_candidates']++;
            if ($isPresent)      $overallSummary['total_present']++;
            if ($isCheckedIn)    $overallSummary['checked_in']++;
            if ($isSubmitted)    $overallSummary['submitted']++;
            if ($isFlagged)      $overallSummary['flagged']++;
            if ($isNotCheckedIn) $overallSummary['yet_to_appear']++;
        }

        // Sort departments: standard departments first, 'Unmapped' last
        uksort($hierarchy, function ($a, $b) {
            if ($a === 'Unmapped / External Registry') return 1;
            if ($b === 'Unmapped / External Registry') return -1;
            return strcasecmp($a, $b);
        });

        // Sort levels within each department numerically (100L, 200L, ..., UNASSIGNED last)
        foreach ($hierarchy as &$deptGroup) {
            uksort($deptGroup['levels'], function ($a, $b) {
                if ($a === 'UNASSIGNED') return 1;
                if ($b === 'UNASSIGNED') return -1;
                $na = (int) preg_replace('/[^0-9]/', '', $a);
                $nb = (int) preg_replace('/[^0-9]/', '', $b);
                return $na <=> $nb;
            });

            // Sort students by full_name within level
            foreach ($deptGroup['levels'] as &$lvlGroup) {
                usort($lvlGroup['students'], function ($sa, $sb) {
                    return strcasecmp($sa['full_name'] ?? '', $sb['full_name'] ?? '');
                });
            }
            unset($lvlGroup);
        }
        unset($deptGroup);

        if ($overallSummary['total_present'] > 0) {
            $overallSummary['submit_rate'] = round(($overallSummary['submitted'] / $overallSummary['total_present']) * 100);
        }

        return [
            'hierarchy'             => $hierarchy,
            'overall_summary'       => $overallSummary,
            'total_students_count'  => $filtered->count(),
            'total_departments_count' => count($hierarchy),
            'timetable'             => $timetable,
            'filters_applied'       => $filters,
        ];
    }

    /**
     * Build the Department -> Level -> Students verification logs hierarchy.
     * Multiple scans belonging to the same student are grouped under that student.
     *
     * @param array{
     *     q?: string|null,
     *     decision?: string|null,
     *     examiner_id?: int|string|null,
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     department?: string|null,
     *     level?: string|null
     * } $filters
     * @return array<string, mixed>
     */
    public function getGroupedVerificationLogs(array $filters = []): array
    {
        if (!Schema::hasTable('verification_logs')) {
            return [
                'hierarchy'       => [],
                'overall_summary' => [
                    'total_scans'     => 0,
                    'approved_scans'  => 0,
                    'rejected_scans'  => 0,
                    'repeated_scans'  => 0,
                    'total_students'  => 0,
                ],
                'total_students_count'    => 0,
                'total_departments_count' => 0,
            ];
        }

        $query = DB::table('verification_logs')
            ->leftJoin('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->leftJoin('examiners', 'verification_logs.examiner_id', '=', 'examiners.examiner_id')
            ->leftJoin('timetables', 'verification_logs.timetable_id', '=', 'timetables.id')
            ->select(
                'verification_logs.*',
                'qr_tokens.student_id as matric_no',
                'examiners.full_name as examiner_name',
                'examiners.username as examiner_username',
                'timetables.course_code',
                'timetables.course_title'
            )
            ->orderByDesc('verification_logs.timestamp');

        if (!empty($filters['decision'])) {
            $dec = strtoupper(trim((string) $filters['decision']));
            if (in_array($dec, ['APPROVED', 'REJECTED', 'DUPLICATE'], true)) {
                $query->where('verification_logs.decision', $dec);
            }
        }

        if (!empty($filters['examiner_id'])) {
            $query->where('verification_logs.examiner_id', (int) $filters['examiner_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('verification_logs.timestamp', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('verification_logs.timestamp', '<=', $filters['date_to']);
        }

        $logRows = $query->get();

        // 1. Group logs by student matriculation number
        $studentsMap = [];
        foreach ($logRows as $log) {
            $norm = $this->matricService->normalize((string) ($log->matric_no ?? ''));
            if ($norm === '') {
                // If token had no student_id, fallback to 'UNMAPPED_LOG_' . $log->log_id
                $norm = 'UNKNOWN_' . $log->log_id;
            }

            if (!isset($studentsMap[$norm])) {
                $studentsMap[$norm] = [
                    'matric_no'       => str_starts_with($norm, 'UNKNOWN_') ? 'Unknown / Malformed QR' : $norm,
                    'raw_norm'        => $norm,
                    'scans'           => [],
                    'total_scans'     => 0,
                    'approved_count'  => 0,
                    'rejected_count'  => 0,
                    'repeated_count'  => 0,
                    'latest_scan'     => null,
                ];
            }

            $scanItem = [
                'log_id'              => $log->log_id,
                'decision'            => $log->decision,
                'timestamp'           => $log->timestamp,
                'device_fp'           => $log->device_fp,
                'ip_address'          => $log->ip_address,
                'rejection_reason'    => $log->rejection_reason,
                'scan_classification' => $log->scan_classification ?? 'regular',
                'examiner_name'       => $log->examiner_name ?? $log->examiner_username ?? 'Unknown',
                'course_code'         => $log->course_code,
                'course_title'        => $log->course_title,
                'timetable_id'        => $log->timetable_id,
            ];

            $studentsMap[$norm]['scans'][] = $scanItem;
            $studentsMap[$norm]['total_scans']++;

            if ($log->decision === 'APPROVED') {
                $studentsMap[$norm]['approved_count']++;
            } elseif ($log->decision === 'REJECTED') {
                $studentsMap[$norm]['rejected_count']++;
            } elseif ($log->decision === 'DUPLICATE') {
                $studentsMap[$norm]['repeated_count']++;
            }

            if ($studentsMap[$norm]['latest_scan'] === null) {
                $studentsMap[$norm]['latest_scan'] = $scanItem;
            }
        }

        // 2. Resolve students against Official Student Registry & Portal accounts
        $validMatrics = array_filter(array_keys($studentsMap), fn ($m) => !str_starts_with($m, 'UNKNOWN_'));

        $registryMap = [];
        if (!empty($validMatrics) && Schema::hasTable('official_students')) {
            $officialRows = DB::table('official_students')
                ->whereIn('matric_number', $validMatrics)
                ->orWhereIn('normalized_matric_number', $validMatrics)
                ->get();

            foreach ($officialRows as $off) {
                $n1 = $this->matricService->normalize((string) $off->matric_number);
                $n2 = $off->normalized_matric_number ? $this->matricService->normalize((string) $off->normalized_matric_number) : null;
                if ($n1) $registryMap[$n1] = $off;
                if ($n2) $registryMap[$n2] = $off;
            }
        }

        $portalMap = [];
        if (!empty($validMatrics) && Schema::hasTable('students')) {
            $portalRows = DB::table('students')
                ->whereIn('matric_no', $validMatrics)
                ->get();

            foreach ($portalRows as $port) {
                $n = $this->matricService->normalize((string) $port->matric_no);
                if ($n) $portalMap[$n] = $port;
            }
        }

        // 3. Enrich student objects
        $enriched = [];
        foreach ($studentsMap as $norm => $item) {
            $official = $registryMap[$norm] ?? null;
            $portal   = $portalMap[$norm] ?? null;

            if ($official) {
                $deptName = trim((string) ($official->department ?? '')) ?: 'Unassigned Department';
                $rawLevel = trim((string) ($official->level ?? ''));
                $cleanLevelNum = preg_replace('/[^0-9]/', '', $rawLevel);
                $levelKey = $cleanLevelNum !== '' ? ($cleanLevelNum . 'L') : 'UNASSIGNED';
                $levelLabel = $cleanLevelNum !== '' ? ($cleanLevelNum . ' Level') : 'Unassigned Level';

                $item['full_name']            = $official->full_name ?? ($portal->full_name ?? 'Official Student');
                $item['department']           = $deptName;
                $item['department_code']      = $official->department_code ?? null;
                $item['faculty']              = $official->faculty ?? null;
                $item['level']                = $rawLevel ?: null;
                $item['level_key']            = $levelKey;
                $item['level_label']          = $levelLabel;
                $item['is_registry_verified'] = true;
            } else {
                $item['full_name']            = $portal->full_name ?? (str_starts_with($norm, 'UNKNOWN_') ? 'Unresolved Pass Scan' : "Unregistered Student ({$norm})");
                $item['department']           = 'Unmapped / External Registry';
                $item['department_code']      = null;
                $item['faculty']              = null;
                $item['level']                = null;
                $item['level_key']            = 'UNASSIGNED';
                $item['level_label']          = 'Unassigned Level';
                $item['is_registry_verified'] = false;
            }

            $item['profile_photo_path'] = $portal->profile_photo_path ?? $portal->photo_path ?? null;

            $enriched[] = $item;
        }

        // 4. Apply text search, department, and level filters
        $filtered = collect($enriched)->filter(function ($st) use ($filters) {
            if (!empty($filters['q'])) {
                $q = strtolower(trim((string) $filters['q']));
                $nameMatch = str_contains(strtolower($st['full_name'] ?? ''), $q);
                $matricMatch = str_contains(strtolower($st['matric_no'] ?? ''), $q);
                if (!$nameMatch && !$matricMatch) {
                    return false;
                }
            }

            if (!empty($filters['department'])) {
                $targetDept = strtolower(trim((string) $filters['department']));
                if (strtolower(trim($st['department'] ?? '')) !== $targetDept) {
                    return false;
                }
            }

            if (!empty($filters['level'])) {
                $targetLevel = strtolower(trim((string) $filters['level']));
                $targetLevelDigits = preg_replace('/[^0-9]/', '', $targetLevel);
                if ($targetLevel === 'unassigned' || $targetLevel === 'unassigned level') {
                    if ($st['level_key'] !== 'UNASSIGNED') {
                        return false;
                    }
                } else {
                    $stLevelDigits = preg_replace('/[^0-9]/', '', (string) ($st['level'] ?? ''));
                    if ($stLevelDigits !== $targetLevelDigits) {
                        return false;
                    }
                }
            }

            return true;
        })->values();

        // 5. Assemble hierarchy: Department -> Level -> Students
        $hierarchy = [];
        $overallSummary = [
            'total_scans'    => 0,
            'approved_scans' => 0,
            'rejected_scans' => 0,
            'repeated_scans' => 0,
            'total_students' => $filtered->count(),
        ];

        foreach ($filtered as $st) {
            $deptName   = $st['department'];
            $levelKey   = $st['level_key'];
            $levelLabel = $st['level_label'];

            if (!isset($hierarchy[$deptName])) {
                $hierarchy[$deptName] = [
                    'department_name' => $deptName,
                    'department_code' => $st['department_code'] ?? null,
                    'faculty'         => $st['faculty'] ?? null,
                    'summary'         => [
                        'total_students' => 0,
                        'total_scans'    => 0,
                        'approved_scans' => 0,
                        'rejected_scans' => 0,
                        'repeated_scans' => 0,
                    ],
                    'levels' => [],
                ];
            }

            if (!isset($hierarchy[$deptName]['levels'][$levelKey])) {
                $hierarchy[$deptName]['levels'][$levelKey] = [
                    'level_key'   => $levelKey,
                    'level_label' => $levelLabel,
                    'raw_level'   => $st['level'],
                    'summary'     => [
                        'total_students' => 0,
                        'total_scans'    => 0,
                        'approved_scans' => 0,
                        'rejected_scans' => 0,
                        'repeated_scans' => 0,
                    ],
                    'students' => [],
                ];
            }

            $hierarchy[$deptName]['levels'][$levelKey]['students'][] = $st;

            // Stats
            $scansCount = $st['total_scans'];
            $appCount   = $st['approved_count'];
            $rejCount   = $st['rejected_count'];
            $repCount   = $st['repeated_count'];

            $hierarchy[$deptName]['levels'][$levelKey]['summary']['total_students']++;
            $hierarchy[$deptName]['levels'][$levelKey]['summary']['total_scans']    += $scansCount;
            $hierarchy[$deptName]['levels'][$levelKey]['summary']['approved_scans'] += $appCount;
            $hierarchy[$deptName]['levels'][$levelKey]['summary']['rejected_scans'] += $rejCount;
            $hierarchy[$deptName]['levels'][$levelKey]['summary']['repeated_scans'] += $repCount;

            $hierarchy[$deptName]['summary']['total_students']++;
            $hierarchy[$deptName]['summary']['total_scans']    += $scansCount;
            $hierarchy[$deptName]['summary']['approved_scans'] += $appCount;
            $hierarchy[$deptName]['summary']['rejected_scans'] += $rejCount;
            $hierarchy[$deptName]['summary']['repeated_scans'] += $repCount;

            $overallSummary['total_scans']    += $scansCount;
            $overallSummary['approved_scans'] += $appCount;
            $overallSummary['rejected_scans'] += $rejCount;
            $overallSummary['repeated_scans'] += $repCount;
        }

        // Sort departments
        uksort($hierarchy, function ($a, $b) {
            if ($a === 'Unmapped / External Registry') return 1;
            if ($b === 'Unmapped / External Registry') return -1;
            return strcasecmp($a, $b);
        });

        // Sort levels within department
        foreach ($hierarchy as &$deptGroup) {
            uksort($deptGroup['levels'], function ($a, $b) {
                if ($a === 'UNASSIGNED') return 1;
                if ($b === 'UNASSIGNED') return -1;
                $na = (int) preg_replace('/[^0-9]/', '', $a);
                $nb = (int) preg_replace('/[^0-9]/', '', $b);
                return $na <=> $nb;
            });

            foreach ($deptGroup['levels'] as &$lvlGroup) {
                usort($lvlGroup['students'], function ($sa, $sb) {
                    return strcasecmp($sa['full_name'] ?? '', $sb['full_name'] ?? '');
                });
            }
            unset($lvlGroup);
        }
        unset($deptGroup);

        return [
            'hierarchy'               => $hierarchy,
            'overall_summary'         => $overallSummary,
            'total_students_count'    => $filtered->count(),
            'total_departments_count' => count($hierarchy),
        ];
    }
}
