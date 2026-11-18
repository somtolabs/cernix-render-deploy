<?php

namespace App\Services;

use App\Models\OfficialStudent;
use App\Models\StudentRegistryImport;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class StudentRegistryImportService
{
    /** Mandatory columns for student registry CSV */
    public const REQUIRED_COLUMNS = [
        'matric_number',
        'full_name',
    ];

    /** Supported optional columns */
    public const OPTIONAL_COLUMNS = [
        'department',
        'faculty',
        'level',
        'programme',
        'academic_session',
        'status',
    ];

    /** Header aliases to accept common institutional column labels */
    public const HEADER_ALIASES = [
        'matric_no'        => 'matric_number',
        'matric'           => 'matric_number',
        'mat_no'           => 'matric_number',
        'student_id'       => 'matric_number',
        'registration_no'  => 'matric_number',
        'reg_no'           => 'matric_number',
        'name'             => 'full_name',
        'student_name'     => 'full_name',
        'fullname'         => 'full_name',
        'dept'             => 'department',
        'dept_name'        => 'department',
        'department_name'  => 'department',
        'fac'              => 'faculty',
        'faculty_name'     => 'faculty',
        'current_level'    => 'level',
        'academic_level'   => 'level',
        'program'          => 'programme',
        'course_of_study'  => 'programme',
        'session'          => 'academic_session',
        'account_status'   => 'status',
        'student_status'   => 'status',
    ];

    /** Fields evaluated for conflicts when re-importing existing records */
    public const CONFLICT_FIELDS = ['full_name', 'department', 'faculty', 'level'];

    /** Institutional descriptions for duplicate and identity classifications */
    public const CLASSIFICATION_LABELS = [
        'new_student'            => 'Category A: New Student',
        'existing_no_changes'    => 'Category B: Existing Record, No Changes',
        'existing_safe_update'   => 'Category C: Existing Record, Safe Update',
        'duplicate_in_file'      => 'Category D: Duplicate Within File',
        'duplicate_across_files' => 'Category E: Duplicate Across Files',
        'identity_conflict'      => 'Category F: Identity Conflict',
        'field_conflict'         => 'Category C: Safe Field Update',
    ];

    public function __construct(
        private readonly MatriculationNumberService $matricService,
        private readonly AuditService $auditService,
    ) {}

    /**
     * Determine the active academic session start year.
     * Sourced dynamically in order of priority:
     * 1. System setting (cernix_settings)
     * 2. Active exam session (exam_sessions.academic_year)
     * 3. Config fallback (config/academic.php -> current_session_start_year)
     */
    public function getCurrentSessionStartYear(): int
    {
        // 1. Check settings table
        if (Schema::hasTable('cernix_settings')) {
            $val = DB::table('cernix_settings')->where('key', 'current_session_start_year')->value('value');
            if ($val && is_numeric($val) && (int) $val >= 2000) {
                return (int) $val;
            }
        }

        // 2. Check active exam_sessions table
        if (Schema::hasTable('exam_sessions')) {
            $activeSession = DB::table('exam_sessions')
                ->where('is_active', true)
                ->orderByDesc('session_id')
                ->first();

            if (! $activeSession) {
                $activeSession = DB::table('exam_sessions')->orderByDesc('session_id')->first();
            }

            if ($activeSession && ! empty($activeSession->academic_year)) {
                if (preg_match('/^(\d{4})/i', trim((string) $activeSession->academic_year), $matches)) {
                    return (int) $matches[1];
                }
            }
        }

        // 3. Fallback to config/academic.php
        return (int) config('academic.current_session_start_year', 2025);
    }

    /**
     * Compute suggested academic level from admission year.
     * Formula: suggested_level = ((current_session_start_year - admission_year) + 1) * 100
     * Clamped to min_level (100) and max_level (500).
     *
     * @param int|string|null $admissionYear
     * @return string|null e.g. "100", "200", "300", "400", "500", or null if undetermined
     */
    public function computeSuggestedLevel(int|string|null $admissionYear): ?string
    {
        if (blank($admissionYear) || ! is_numeric($admissionYear)) {
            return null;
        }

        $admYear = (int) $admissionYear;
        if ($admYear < 1980 || $admYear > 2100) {
            return null;
        }

        $sessionStartYear = $this->getCurrentSessionStartYear();
        $diffYears = ($sessionStartYear - $admYear) + 1;

        $maxLevel = (int) config('academic.max_level', 500);
        $minLevel = (int) config('academic.min_level', 100);

        $maxNum = max(1, (int) ($maxLevel / 100)); // 5
        $minNum = max(1, (int) ($minLevel / 100)); // 1

        $clamped = max($minNum, min($maxNum, $diffYears));

        return (string) ($clamped * 100);
    }

    /**
     * Resolve preview level display and suggestion indicators.
     *
     * @param array<string, string> $extracted
     * @param object|null $existing
     * @param \App\Services\MatricParseResult $parsed
     * @return array{level: string, is_level_suggested: bool, suggested_level: ?string, level_source: string}
     */
    private function resolvePreviewLevel(array $extracted, ?object $existing, $parsed): array
    {
        $supplied = trim((string) ($extracted['level'] ?? ''));
        if ($supplied !== '') {
            return [
                'level'              => $supplied,
                'is_level_suggested' => false,
                'suggested_level'    => null,
                'level_source'       => 'csv',
            ];
        }

        if ($existing && $existing->level !== null && trim((string) $existing->level) !== '') {
            return [
                'level'              => (string) $existing->level,
                'is_level_suggested' => false,
                'suggested_level'    => null,
                'level_source'       => 'existing',
            ];
        }

        $suggested = $this->computeSuggestedLevel($parsed->admission_year);
        if ($suggested !== null) {
            return [
                'level'              => $suggested,
                'is_level_suggested' => true,
                'suggested_level'    => $suggested,
                'level_source'       => 'suggested',
            ];
        }

        return [
            'level'              => '—',
            'is_level_suggested' => false,
            'suggested_level'    => null,
            'level_source'       => 'none',
        ];
    }

    /**
     * Preview and analyze single or multiple registry CSV documents without committing changes.
     * Distinguishes all duplicate classifications (A through F).
     *
     * @param UploadedFile|array<int, UploadedFile> $files
     * @return array<string, mixed>
     */
    public function preview(UploadedFile|array $files): array
    {
        $fileList = is_array($files) ? $files : [$files];
        if (empty($fileList)) {
            throw new RuntimeException('No CSV file was provided for preview.');
        }

        $allRows              = [];
        $totalRawRows         = 0;
        $invalidCount         = 0;
        $missingNameCount     = 0;
        $fileDuplicateCount   = 0;
        $crossFileDuplicateCount = 0;
        $unmappedCodeCount    = 0;
        $uncertainCount       = 0;
        $identityConflictCount= 0;
        $fieldConflictCount   = 0;
        $suggestedLevelCount  = 0;
        $errors               = [];
        $conflicts            = [];

        // 1. Parse and extract rows from all files
        foreach ($fileList as $file) {
            $filename = $file->getClientOriginalName();
            $handle = fopen($file->getRealPath(), 'rb');
            if ($handle === false) {
                throw new RuntimeException("Could not open file: {$filename}");
            }

            try {
                $header = fgetcsv($handle);
                if (! is_array($header)) {
                    throw new RuntimeException("The uploaded file '{$filename}' is empty.");
                }

                $columnIndexes = $this->resolveColumnIndexes($header);
                $missing = array_values(array_diff(self::REQUIRED_COLUMNS, array_keys($columnIndexes)));
                if ($missing) {
                    throw new RuntimeException("File '{$filename}' is missing required columns: " . implode(', ', $missing) . '. Minimum format requires: matric_number, full_name.');
                }

                $rowNumber = 1;
                while (($row = fgetcsv($handle)) !== false) {
                    $rowNumber++;
                    if ($row === [null] || count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                        continue;
                    }

                    $totalRawRows++;
                    $extracted = $this->extractRowData($row, $columnIndexes);
                    $allRows[] = [
                        'source_file' => $filename,
                        'source_row'  => $rowNumber,
                        'extracted'   => $extracted,
                    ];
                }
            } finally {
                fclose($handle);
            }
        }

        // 2. In-memory tracking and database pre-resolution
        $seenInBatch   = []; // normalized => list of occurrences
        $parsedMatrics = [];
        $previewRows   = [];

        // Pre-fetch existing database records for efficiency
        $candidateMatrics = [];
        foreach ($allRows as $item) {
            $raw = $item['extracted']['raw_matric'];
            if ($raw !== '') {
                $norm = $this->matricService->normalize($raw);
                if ($norm !== '') {
                    $candidateMatrics[$norm] = true;
                }
            }
        }

        $existingDbRecords = [];
        if (! empty($candidateMatrics) && Schema::hasTable('official_students')) {
            $keys = array_keys($candidateMatrics);
            $query = DB::table('official_students')
                ->whereIn('matric_number', $keys);
            if (Schema::hasColumn('official_students', 'normalized_matric_number')) {
                $query->orWhereIn('normalized_matric_number', $keys);
            }
            $existingDbRecords = $query->get()->keyBy(function ($item) {
                return $item->normalized_matric_number ?: $this->matricService->normalize($item->matric_number);
            })->all();
        }

        // 3. Classify each row
        $distinctIdentities = [];
        $newStudentsCount = 0;
        $existingNoChangesCount = 0;
        $existingSafeUpdateCount = 0;

        foreach ($allRows as $item) {
            $sourceFile = $item['source_file'];
            $sourceRow  = $item['source_row'];
            $extracted  = $item['extracted'];
            $rawMatric  = $extracted['raw_matric'];
            $fullName   = $extracted['full_name'];

            // Missing full name
            if ($fullName === '') {
                $missingNameCount++;
                $invalidCount++;
                $errors[] = [
                    'file'   => $sourceFile,
                    'row'    => $sourceRow,
                    'reason' => 'Missing full name',
                    'matric' => $rawMatric,
                ];
                continue;
            }

            // Parse structure
            $parsed = $this->matricService->parse($rawMatric, verifyRegistry: false);
            if (! $parsed->isValidStructure()) {
                $invalidCount++;
                $errors[] = [
                    'file'   => $sourceFile,
                    'row'    => $sourceRow,
                    'reason' => $parsed->errors[0] ?? 'Invalid matriculation number format',
                    'matric' => $rawMatric,
                ];
                continue;
            }

            $normalized = $parsed->normalized;
            $parsedMatrics[$normalized] = $parsed;

            if ($parsed->isAnomalousCandidate()) {
                $uncertainCount++;
            }
            if ($parsed->isCanonical() && ! $parsed->isMapped()) {
                $unmappedCodeCount++;
            }

            // Check duplicate within upload batch
            $isDuplicateInBatch = isset($seenInBatch[$normalized]);
            if ($isDuplicateInBatch) {
                $firstSeen = $seenInBatch[$normalized][0];
                $sameFile = ($firstSeen['source_file'] === $sourceFile);

                if ($sameFile) {
                    $fileDuplicateCount++;
                    $classification = 'duplicate_in_file';
                } else {
                    $crossFileDuplicateCount++;
                    $classification = 'duplicate_across_files';
                }

                // Check if in-batch repetition has conflicting details
                $namesMatch = (strcasecmp($fullName, $firstSeen['full_name']) === 0);
                if (! $namesMatch) {
                    $identityConflictCount++;
                    $conflicts[] = [
                        'matric'          => $normalized,
                        'type'            => $sameFile ? 'duplicate_in_file_conflict' : 'duplicate_across_files_conflict',
                        'source_file'     => $sourceFile,
                        'source_row'      => $sourceRow,
                        'first_seen_file' => $firstSeen['source_file'],
                        'first_seen_row'  => $firstSeen['source_row'],
                        'first_seen_name' => $firstSeen['full_name'],
                        'incoming_name'   => $fullName,
                        'differing_fields'=> ['full_name' => ['from' => $firstSeen['full_name'], 'to' => $fullName]],
                        'message'         => "Conflicting student names detected for matric {$normalized} in upload batch.",
                    ];
                }

                $seenInBatch[$normalized][] = [
                    'source_file' => $sourceFile,
                    'source_row'  => $sourceRow,
                    'full_name'   => $fullName,
                    'extracted'   => $extracted,
                ];

                if (count($previewRows) < 25) {
                    $lvlInfo = $this->resolvePreviewLevel($extracted, null, $parsed);
                    $previewRows[] = [
                        'file'                 => $sourceFile,
                        'row'                  => $sourceRow,
                        'matric_number'        => $normalized,
                        'full_name'            => $fullName,
                        'department'           => $extracted['department'] ?: ($parsed->department_name ?? '—'),
                        'faculty'              => $extracted['faculty'] ?: ($parsed->faculty_name ?? '—'),
                        'level'                => $lvlInfo['level'],
                        'suggested_level'      => $lvlInfo['suggested_level'],
                        'is_level_suggested'   => $lvlInfo['is_level_suggested'],
                        'level_source'         => $lvlInfo['level_source'],
                        'classification'       => $classification,
                        'classification_label' => self::CLASSIFICATION_LABELS[$classification] ?? $classification,
                        'format'               => $parsed->format,
                        'is_mapped'            => $parsed->isMapped(),
                        'is_anomalous'         => $parsed->isAnomalousCandidate(),
                    ];
                }

                continue;
            }

            // First time seeing this matric in the batch
            $seenInBatch[$normalized] = [[
                'source_file' => $sourceFile,
                'source_row'  => $sourceRow,
                'full_name'   => $fullName,
                'extracted'   => $extracted,
            ]];

            $existing = $existingDbRecords[$normalized] ?? null;

            if (! $existing) {
                // Category A: New Student
                $classification = 'new_student';
                $newStudentsCount++;
                $distinctIdentities[$normalized] = true;
            } else {
                // Compare with existing DB identity
                $dbName = trim((string) $existing->full_name);
                $namesMatch = (strcasecmp($fullName, $dbName) === 0);

                if (! $namesMatch) {
                    // Category F: Matriculation Identity Conflict (Different Name)
                    $classification = 'identity_conflict';
                    $identityConflictCount++;
                    $conflicts[] = [
                        'matric'          => $normalized,
                        'type'            => 'identity_conflict',
                        'source_file'     => $sourceFile,
                        'source_row'      => $sourceRow,
                        'existing_record' => [
                            'full_name'  => $existing->full_name,
                            'department' => $existing->department,
                            'faculty'    => $existing->faculty,
                            'level'      => $existing->level,
                            'status'     => $existing->status,
                        ],
                        'incoming_record' => [
                            'full_name'  => $fullName,
                            'department' => $extracted['department'] ?: ($parsed->department_name ?? null),
                            'faculty'    => $extracted['faculty'] ?: ($parsed->faculty_name ?? null),
                            'level'      => $extracted['level'] ?: null,
                        ],
                        'differing_fields'=> ['full_name' => ['from' => $existing->full_name, 'to' => $fullName]],
                        'message'         => "Matriculation number {$normalized} belongs to '{$existing->full_name}' in official registry, but incoming upload states '{$fullName}'.",
                    ];
                } else {
                    // Name matches: check if incoming supplies new information or conflicts on optional fields
                    $hasSafeUpdates = false;
                    $hasConflicts   = false;
                    $changes        = [];

                    foreach (self::CONFLICT_FIELDS as $field) {
                        if ($field === 'full_name') continue;

                        $oldVal = trim((string) ($existing->{$field} ?? ''));
                        $newVal = trim((string) ($extracted[$field] ?: ($parsed->{$field . '_name'} ?? '')));

                        if ($oldVal === '' && $newVal !== '') {
                            $hasSafeUpdates = true;
                        } elseif ($oldVal !== '' && $newVal !== '' && strtolower($oldVal) !== strtolower($newVal)) {
                            $hasConflicts = true;
                            $changes[$field] = ['from' => $oldVal, 'to' => $newVal];
                        }
                    }

                    if ($hasConflicts) {
                        $classification = 'field_conflict';
                        $fieldConflictCount++;
                        $conflicts[] = [
                            'matric'          => $normalized,
                            'type'            => 'field_conflict',
                            'source_file'     => $sourceFile,
                            'source_row'      => $sourceRow,
                            'existing_record' => [
                                'full_name'  => $existing->full_name,
                                'department' => $existing->department,
                                'faculty'    => $existing->faculty,
                                'level'      => $existing->level,
                                'status'     => $existing->status,
                            ],
                            'incoming_record' => [
                                'full_name'  => $fullName,
                                'department' => $extracted['department'] ?: ($parsed->department_name ?? null),
                                'faculty'    => $extracted['faculty'] ?: ($parsed->faculty_name ?? null),
                                'level'      => $extracted['level'] ?: null,
                            ],
                            'differing_fields'=> $changes,
                            'message'         => "Optional institutional fields differ from official registry for matric {$normalized}.",
                        ];
                    } elseif ($hasSafeUpdates) {
                        // Category C: Existing Student, Safe Update
                        $classification = 'existing_safe_update';
                        $existingSafeUpdateCount++;
                    } else {
                        // Category B: Existing Student, No Changes
                        $classification = 'existing_no_changes';
                        $existingNoChangesCount++;
                    }
                }

                $distinctIdentities[$normalized] = true;
            }

            $lvlInfo = $this->resolvePreviewLevel($extracted, $existing, $parsed);
            if ($lvlInfo['is_level_suggested']) {
                $suggestedLevelCount++;
            }

            if (count($previewRows) < 25) {
                $previewRows[] = [
                    'file'                 => $sourceFile,
                    'row'                  => $sourceRow,
                    'matric_number'        => $normalized,
                    'full_name'            => $fullName,
                    'department'           => $extracted['department'] ?: ($parsed->department_name ?? ($existing->department ?? '—')),
                    'faculty'              => $extracted['faculty'] ?: ($parsed->faculty_name ?? ($existing->faculty ?? '—')),
                    'level'                => $lvlInfo['level'],
                    'suggested_level'      => $lvlInfo['suggested_level'],
                    'is_level_suggested'   => $lvlInfo['is_level_suggested'],
                    'level_source'         => $lvlInfo['level_source'],
                    'classification'       => $classification,
                    'classification_label' => self::CLASSIFICATION_LABELS[$classification] ?? $classification,
                    'format'               => $parsed->format,
                    'is_mapped'            => $parsed->isMapped(),
                    'is_anomalous'         => $parsed->isAnomalousCandidate(),
                ];
            }
        }

        return [
            'total_rows'                 => $totalRawRows,
            'files_count'                => count($fileList),
            'distinct_students_count'    => count($distinctIdentities),
            'new_student_count'          => $newStudentsCount,
            'new_valid_count'            => $newStudentsCount,
            'existing_count'             => $existingNoChangesCount + $existingSafeUpdateCount,
            'existing_no_changes_count'  => $existingNoChangesCount,
            'existing_safe_update_count' => $existingSafeUpdateCount,
            'duplicate_in_file_count'    => $fileDuplicateCount,
            'file_duplicate_count'       => $fileDuplicateCount,
            'duplicate_across_files_count'=> $crossFileDuplicateCount,
            'identity_conflict_count'    => $identityConflictCount,
            'field_conflict_count'       => $fieldConflictCount,
            'conflict_count'             => $identityConflictCount + $fieldConflictCount,
            'invalid_count'              => $invalidCount,
            'missing_name_count'         => $missingNameCount,
            'unmapped_code_count'        => $unmappedCodeCount,
            'uncertain_count'            => $uncertainCount,
            'suggested_level_count'      => $suggestedLevelCount,
            'current_session_start_year' => $this->getCurrentSessionStartYear(),
            'preview_rows'               => $previewRows,
            'errors'                     => array_slice($errors, 0, 30),
            'conflicts'                  => array_slice($conflicts, 0, 50),
        ];
    }

    /**
     * Import single or multiple Student Registry CSV files into official_students.
     * Enforces transactional database integrity, safe updates, conflict preservation,
     * and inactive status preservation.
     *
     * @param UploadedFile|array<int, UploadedFile> $files
     */
    public function import(UploadedFile|array $files, ?string $uploadedBy): StudentRegistryImport
    {
        $fileList = is_array($files) ? $files : [$files];
        if (empty($fileList)) {
            throw new RuntimeException('No CSV file was provided for import.');
        }

        $allRows      = [];
        $totalRawRows = 0;
        $filenames    = [];

        // 1. Ingest rows from all provided files
        foreach ($fileList as $file) {
            $filename = $file->getClientOriginalName();
            $filenames[] = $filename;
            $handle = fopen($file->getRealPath(), 'rb');
            if ($handle === false) {
                throw new RuntimeException("Could not open file: {$filename}");
            }

            try {
                $header = fgetcsv($handle);
                if (! is_array($header)) {
                    throw new RuntimeException("The uploaded file '{$filename}' is empty.");
                }

                $columnIndexes = $this->resolveColumnIndexes($header);
                $missing = array_values(array_diff(self::REQUIRED_COLUMNS, array_keys($columnIndexes)));
                if ($missing) {
                    throw new RuntimeException("File '{$filename}' is missing required columns: " . implode(', ', $missing) . '. Minimum format requires: matric_number, full_name.');
                }

                $rowNumber = 1;
                while (($row = fgetcsv($handle)) !== false) {
                    $rowNumber++;
                    if ($row === [null] || count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                        continue;
                    }

                    $totalRawRows++;
                    $extracted = $this->extractRowData($row, $columnIndexes);
                    $allRows[] = [
                        'source_file' => $filename,
                        'source_row'  => $rowNumber,
                        'extracted'   => $extracted,
                    ];
                }
            } finally {
                fclose($handle);
            }
        }

        $importedRows    = 0;
        $skippedRows     = 0;
        $failedRows      = 0;
        $newRows         = 0;
        $updatedRows     = 0;
        $unmappedRows    = 0;
        $anomalousRows   = 0;
        $conflictCount   = 0;
        $suggestedLevelAppliedCount = 0;
        $errors          = [];
        $conflicts       = [];

        // 2. Process all entries within a strict database transaction
        DB::transaction(function () use (
            $allRows,
            &$totalRawRows,
            &$importedRows,
            &$skippedRows,
            &$failedRows,
            &$newRows,
            &$updatedRows,
            &$unmappedRows,
            &$anomalousRows,
            &$conflictCount,
            &$suggestedLevelAppliedCount,
            &$errors,
            &$conflicts
        ) {
            $seenInBatch = []; // normalized => first occurrence

            foreach ($allRows as $item) {
                $sourceFile = $item['source_file'];
                $sourceRow  = $item['source_row'];
                $extracted  = $item['extracted'];
                $rawMatric  = $extracted['raw_matric'];
                $fullName   = $extracted['full_name'];

                // 1. Missing full name
                if ($fullName === '') {
                    $skippedRows++;
                    $failedRows++;
                    $errors[] = [
                        'file'   => $sourceFile,
                        'row'    => $sourceRow,
                        'reason' => 'Missing required value: full_name',
                        'data'   => ['matric_number' => $rawMatric, 'full_name' => ''],
                    ];
                    continue;
                }

                // 2. Parse matriculation number
                $parsed = $this->matricService->parse($rawMatric, verifyRegistry: false);
                if (! $parsed->isValidStructure()) {
                    $skippedRows++;
                    $failedRows++;
                    $errors[] = [
                        'file'   => $sourceFile,
                        'row'    => $sourceRow,
                        'reason' => $parsed->errors[0] ?? "Invalid matriculation number: {$rawMatric}",
                        'data'   => ['matric_number' => $rawMatric, 'full_name' => $fullName],
                    ];
                    continue;
                }

                $normalized = $parsed->normalized;

                // 3. Batch Duplicate Detection (In-File or Cross-File)
                if (isset($seenInBatch[$normalized])) {
                    $firstSeen = $seenInBatch[$normalized];
                    $sameFile = ($firstSeen['source_file'] === $sourceFile);
                    $namesMatch = (strcasecmp($fullName, $firstSeen['full_name']) === 0);

                    if ($sameFile) {
                        $skippedRows++;
                        $failedRows++;
                        $errors[] = [
                            'file'   => $sourceFile,
                            'row'    => $sourceRow,
                            'reason' => "Duplicate matriculation number in upload (first seen on row {$firstSeen['source_row']})",
                            'data'   => ['matric_number' => $normalized, 'full_name' => $fullName],
                        ];
                        if (! $namesMatch) {
                            $conflictCount++;
                            $conflicts[] = [
                                'matric'          => $normalized,
                                'type'            => 'duplicate_in_file_conflict',
                                'source_file'     => $sourceFile,
                                'source_row'      => $sourceRow,
                                'first_seen_file' => $firstSeen['source_file'],
                                'first_seen_row'  => $firstSeen['source_row'],
                                'first_seen_name' => $firstSeen['full_name'],
                                'incoming_name'   => $fullName,
                                'message'         => "Duplicate matric {$normalized} with conflicting name in same file.",
                            ];
                        }
                        continue;
                    } else {
                        // Cross-file duplicate
                        if (! $namesMatch) {
                            $conflictCount++;
                            $skippedRows++;
                            $failedRows++;
                            $conflicts[] = [
                                'matric'          => $normalized,
                                'type'            => 'duplicate_across_files_conflict',
                                'source_file'     => $sourceFile,
                                'source_row'      => $sourceRow,
                                'first_seen_file' => $firstSeen['source_file'],
                                'first_seen_row'  => $firstSeen['source_row'],
                                'first_seen_name' => $firstSeen['full_name'],
                                'incoming_name'   => $fullName,
                                'message'         => "Duplicate matric {$normalized} across files with conflicting names.",
                            ];
                        } else {
                            // Identical repetition across files: count student once, merge safely
                            $skippedRows++;
                        }
                        continue;
                    }
                }

                $seenInBatch[$normalized] = [
                    'source_file' => $sourceFile,
                    'source_row'  => $sourceRow,
                    'full_name'   => $fullName,
                ];

                if ($parsed->isAnomalousCandidate()) {
                    $anomalousRows++;
                }

                if ($parsed->isCanonical() && ! $parsed->isMapped()) {
                    $unmappedRows++;
                }

                // 4. Resolve Department and Faculty
                $suppliedDept = $extracted['department'];
                $suppliedFac  = $extracted['faculty'];

                $finalDept = $suppliedDept !== '' ? $suppliedDept : ($parsed->department_name ?: null);
                $finalFac  = $suppliedFac !== '' ? $suppliedFac : ($parsed->faculty_name ?: null);

                // Inconsistency check: supplied vs decoded
                if ($suppliedDept !== '' && $parsed->department_name && strtolower($suppliedDept) !== strtolower($parsed->department_name)) {
                    $conflictCount++;
                    $conflicts[] = [
                        'matric' => $normalized,
                        'name'   => $fullName,
                        'type'   => 'supplied_department_inconsistency',
                        'fields' => ['department' => ['from' => $parsed->department_name, 'to' => $suppliedDept]],
                        'note'   => "Supplied department '{$suppliedDept}' differs from decoded department '{$parsed->department_name}'.",
                    ];
                }

                if ($suppliedFac !== '' && $parsed->faculty_name && strtolower($suppliedFac) !== strtolower($parsed->faculty_name)) {
                    $conflictCount++;
                    $conflicts[] = [
                        'matric' => $normalized,
                        'name'   => $fullName,
                        'type'   => 'supplied_faculty_inconsistency',
                        'fields' => ['faculty' => ['from' => $parsed->faculty_name, 'to' => $suppliedFac]],
                        'note'   => "Supplied faculty '{$suppliedFac}' differs from decoded faculty '{$parsed->faculty_name}'.",
                    ];
                }

                // 5. Look up existing official record
                $existing = OfficialStudent::where('normalized_matric_number', $normalized)
                    ->orWhere('matric_number', $normalized)
                    ->first();

                // 6. Identity Conflict with existing DB record (Different Name)
                if ($existing) {
                    $dbName = trim((string) $existing->full_name);
                    if (strcasecmp($fullName, $dbName) !== 0) {
                        // SERIOUS DATA INTEGRITY CONFLICT:
                        // Do not overwrite existing record. Do not create another record.
                        $conflictCount++;
                        $skippedRows++;
                        $failedRows++;
                        $conflicts[] = [
                            'matric'          => $normalized,
                            'type'            => 'identity_conflict',
                            'source_file'     => $sourceFile,
                            'source_row'      => $sourceRow,
                            'existing_record' => [
                                'full_name'  => $existing->full_name,
                                'department' => $existing->department,
                                'faculty'    => $existing->faculty,
                                'level'      => $existing->level,
                                'status'     => $existing->status,
                            ],
                            'incoming_record' => [
                                'full_name'  => $fullName,
                                'department' => $finalDept,
                                'faculty'    => $finalFac,
                                'level'      => $extracted['level'] ?: null,
                            ],
                            'message'         => "Identity conflict: Matric {$normalized} is officially registered to '{$existing->full_name}', incoming row states '{$fullName}'. Existing record preserved.",
                        ];
                        continue;
                    }
                }

                // 7. Safe attribute merging:
                // Level resolution:
                // - If explicitly supplied in CSV, use supplied level.
                // - If omitted in CSV and existing registry record already has a non-null level, PRESERVE it (never overwrite).
                // - If omitted in CSV and student is new (or existing level is null), compute suggested level.
                $suppliedLevel = trim((string) ($extracted['level'] ?? ''));
                if ($suppliedLevel !== '') {
                    $finalLevel = $suppliedLevel;
                } elseif ($existing && $existing->level !== null && trim((string) $existing->level) !== '') {
                    $finalLevel = $existing->level;
                } else {
                    $finalLevel = $this->computeSuggestedLevel($parsed->admission_year);
                    if ($finalLevel !== null) {
                        $suggestedLevelAppliedCount++;
                    }
                }

                // Status handling:
                // If supplied explicitly as active/inactive, use it.
                // If omitted, PRESERVE existing status (e.g. keep inactive students inactive!).
                // If brand new record without status, default to 'active'.
                $suppliedStatus = $extracted['status'];
                if (in_array($suppliedStatus, ['active', 'inactive'], true)) {
                    $finalStatus = $suppliedStatus;
                } else {
                    $finalStatus = $existing->status ?? 'active';
                }

                $finalProgramme = $extracted['programme'] !== '' ? $extracted['programme'] : ($existing->programme ?? null);
                $finalSession   = $extracted['academic_session'] !== '' ? $extracted['academic_session'] : ($existing->academic_session ?? null);

                // Department & Faculty preservation: don't overwrite existing non-null with null
                $recordDept = $finalDept ?: ($existing->department ?? null);
                $recordFac  = $finalFac ?: ($existing->faculty ?? null);

                $recordData = [
                    'matric_number'            => $normalized,
                    'normalized_matric_number' => $normalized,
                    'admission_year'           => $parsed->admission_year,
                    'faculty_code'             => $parsed->faculty_code,
                    'department_code'          => $parsed->department_code,
                    'full_name'                => $fullName,
                    'department'               => $recordDept,
                    'faculty'                  => $recordFac,
                    'level'                    => $finalLevel,
                    'programme'                => $finalProgramme,
                    'academic_session'         => $finalSession,
                    'status'                   => $finalStatus,
                ];

                try {
                    if ($existing) {
                        // Check for changes
                        $changes = [];
                        foreach (self::CONFLICT_FIELDS as $field) {
                            $oldVal = trim((string) ($existing->{$field} ?? ''));
                            $newVal = trim((string) ($recordData[$field] ?? ''));
                            if ($newVal !== '' && strtolower($oldVal) !== strtolower($newVal)) {
                                $changes[$field] = ['from' => $oldVal, 'to' => $newVal];
                            }
                        }

                        if ($changes) {
                            $conflictCount++;
                            $conflicts[] = [
                                'matric' => $normalized,
                                'name'   => $fullName,
                                'type'   => 'existing_record_updated',
                                'fields' => $changes,
                            ];
                        }

                        $existing->update($recordData);
                        $updatedRows++;

                        // Sync to registered student portal account (without touching credentials)
                        if (! empty($changes)) {
                            $this->syncRegisteredStudent($recordData);
                        }
                    } else {
                        OfficialStudent::create($recordData);
                        $newRows++;
                    }

                    $importedRows++;
                } catch (UniqueConstraintViolationException|QueryException $e) {
                    // Concurrent import collision or constraint violation caught gracefully
                    $skippedRows++;
                    $failedRows++;
                    $errors[] = [
                        'file'   => $sourceFile,
                        'row'    => $sourceRow,
                        'reason' => "Database uniqueness constraint prevented inserting duplicate matric {$normalized}: " . $e->getMessage(),
                        'data'   => ['matric_number' => $normalized, 'full_name' => $fullName],
                    ];
                }
            }
        });

        $originalFilename = count($filenames) === 1 ? $filenames[0] : implode(', ', $filenames);

        return StudentRegistryImport::create([
            'uploaded_by'       => $uploadedBy,
            'original_filename' => $originalFilename,
            'total_rows'        => $totalRawRows,
            'imported_rows'     => $importedRows,
            'skipped_rows'      => $skippedRows,
            'failed_rows'       => $failedRows,
            'error_summary'     => [
                'errors'                        => array_slice($errors, 0, 30),
                'conflicts'                     => array_slice($conflicts, 0, 50),
                'new_records'                   => $newRows,
                'updated_records'               => $updatedRows,
                'conflict_count'                => $conflictCount,
                'unmapped_records'              => $unmappedRows,
                'anomalous_format_count'        => $anomalousRows,
                'suggested_level_applied_count' => $suggestedLevelAppliedCount,
                'session_start_year'            => $this->getCurrentSessionStartYear(),
                'files_processed'               => $filenames,
            ],
        ]);
    }

    /**
     * Resolve a flagged conflict on an official student record.
     * Records full audit trail including previous value, new value, administrator, and timestamp.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function resolveConflict(
        string $matricNumber,
        string $action,
        array $data = [],
        ?string $resolvedBy = null,
        ?int $importId = null
    ): array {
        $normalized = $this->matricService->normalize($matricNumber);
        $student = OfficialStudent::where('normalized_matric_number', $normalized)
            ->orWhere('matric_number', $normalized)
            ->first();

        if (! $student) {
            throw new RuntimeException("Official student with matriculation number '{$matricNumber}' was not found.");
        }

        $beforeValues = $student->only(['matric_number', 'full_name', 'department', 'faculty', 'level', 'status']);
        $afterValues  = $beforeValues;

        switch ($action) {
            case 'keep_existing':
                // Do nothing to the record; administrator confirmed existing information is correct
                break;

            case 'apply_incoming':
                $incoming = $data['incoming_record'] ?? [];
                if (! empty($incoming['full_name'])) {
                    $student->full_name = trim((string) $incoming['full_name']);
                }
                if (! empty($incoming['department'])) {
                    $student->department = trim((string) $incoming['department']);
                }
                if (! empty($incoming['faculty'])) {
                    $student->faculty = trim((string) $incoming['faculty']);
                }
                if (! empty($incoming['level'])) {
                    $student->level = trim((string) $incoming['level']);
                }
                $student->save();
                $afterValues = $student->only(['matric_number', 'full_name', 'department', 'faculty', 'level', 'status']);
                $this->syncRegisteredStudent($afterValues);
                break;

            case 'manual_correct':
                $corrections = $data['corrections'] ?? $data;
                if (! empty($corrections['full_name'])) {
                    $student->full_name = trim((string) $corrections['full_name']);
                }
                if (isset($corrections['department'])) {
                    $student->department = trim((string) $corrections['department']) ?: null;
                }
                if (isset($corrections['faculty'])) {
                    $student->faculty = trim((string) $corrections['faculty']) ?: null;
                }
                if (isset($corrections['level'])) {
                    $student->level = trim((string) $corrections['level']) ?: null;
                }
                if (isset($corrections['status']) && in_array($corrections['status'], ['active', 'inactive'], true)) {
                    $student->status = $corrections['status'];
                }
                $student->save();
                $afterValues = $student->only(['matric_number', 'full_name', 'department', 'faculty', 'level', 'status']);
                $this->syncRegisteredStudent($afterValues);
                break;

            case 'exclude':
                // Conflicting incoming record excluded from import. No change to official record.
                break;

            default:
                throw new RuntimeException("Invalid conflict resolution action: '{$action}'. Valid actions: keep_existing, apply_incoming, manual_correct, exclude.");
        }

        // Record immutable audit log
        $actorId = $resolvedBy ?: 'admin';
        $this->auditService->logAction(
            actorId: $actorId,
            actorType: 'admin',
            action: 'student_registry.conflict_resolved',
            metadata: [
                'resolution_action' => $action,
                'import_id'         => $importId,
                'matric_number'     => $normalized,
                'timestamp'         => now()->toIso8601String(),
            ],
            targetType: 'official_student',
            targetId: $normalized,
            beforeValues: $beforeValues,
            afterValues: $afterValues
        );

        return [
            'success'       => true,
            'action'        => $action,
            'matric_number' => $normalized,
            'student'       => $student->fresh(),
        ];
    }

    /**
     * Propagate identity changes to registered student portal profile in `students`.
     * Preserves passwords, photos, photo status, and account credentials.
     *
     * @param array<string, mixed> $record
     */
    private function syncRegisteredStudent(array $record): void
    {
        if (! Schema::hasTable('students')) {
            return;
        }

        $updates = [
            'full_name'  => $record['full_name'],
            'updated_at' => now(),
        ];

        if (! empty($record['level'])) {
            $updates['level'] = $record['level'];
        }

        if (! empty($record['department']) && Schema::hasTable('departments')) {
            $dept = DB::table('departments')
                ->whereRaw('LOWER(dept_name) = ?', [strtolower((string) $record['department'])])
                ->first();

            if ($dept) {
                $updates['department_id']   = $dept->dept_id;
                $updates['department_code'] = $dept->department_code ?? null;
                $updates['faculty_code']    = $dept->faculty_code ?? null;
            }
        }

        DB::table('students')
            ->where('matric_no', $record['matric_number'])
            ->update($updates);
    }

    /**
     * Map CSV header row to recognized canonical column names using aliases.
     *
     * @param array<int, mixed> $header
     * @return array<string, int>
     */
    private function resolveColumnIndexes(array $header): array
    {
        $indexes = [];
        $allowed = array_merge(self::REQUIRED_COLUMNS, self::OPTIONAL_COLUMNS);

        foreach ($header as $index => $label) {
            $clean = $this->normalizeHeader((string) $label);
            $canonical = self::HEADER_ALIASES[$clean] ?? $clean;

            if (in_array($canonical, $allowed, true)) {
                $indexes[$canonical] = $index;
            }
        }

        return $indexes;
    }

    /**
     * Extract structured values from a CSV row given resolved column indexes.
     *
     * @param array<int, mixed> $row
     * @param array<string, int> $indexes
     * @return array<string, string>
     */
    private function extractRowData(array $row, array $indexes): array
    {
        $val = function (string $key) use ($row, $indexes): string {
            if (! isset($indexes[$key])) {
                return '';
            }

            return trim((string) ($row[$indexes[$key]] ?? ''));
        };

        return [
            'raw_matric'       => $val('matric_number'),
            'full_name'        => $val('full_name'),
            'department'       => $val('department'),
            'faculty'          => $val('faculty'),
            'level'            => $val('level'),
            'programme'        => $val('programme'),
            'academic_session' => $val('academic_session'),
            'status'           => strtolower($val('status')),
        ];
    }

    private function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        return strtolower(trim($value));
    }
}
