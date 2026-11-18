<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class MatriculationNumberService
{
    /**
     * Known historical departmental prefixes used in legacy AAUA matriculation formats.
     * Maps prefix acronyms to verified department names.
     */
    public const HISTORICAL_SLASH_PREFIXES = [
        'CSC' => 'Computer Science',
        'SEN' => 'Software Engineering',
        'IFT' => 'Information Technology',
        'CYS' => 'Cyber Security',
        'DTS' => 'Data Science',
    ];

    /**
     * In-memory cache for department code mappings to accelerate batch parsing.
     *
     * @var array<string, object>|null
     */
    private ?array $departmentCodeCache = null;

    /**
     * In-memory cache for departments indexed by lowercase name.
     *
     * @var array<string, object>|null
     */
    private ?array $departmentNameCache = null;

    /**
     * Normalize a raw matriculation number safely.
     *
     * Rules:
     * - Strips BOM and zero-width characters.
     * - Trims leading and trailing whitespace.
     * - Standardizes spacing around slashes for slash-based formats and uppercases ASCII characters.
     * - Removes internal whitespace for digit-only strings (e.g. "25 04 04 001" -> "250404001").
     * - Preserves leading zeroes (no numeric casting).
     * - Never silently removes letters, hyphens, or symbols that could alter identity.
     */
    public function normalize(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        // Strip UTF-8 BOM and zero-width spaces
        $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $raw) ?? $raw;

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        // Handle slash-based format: normalize spaces around slashes and uppercase
        if (str_contains($trimmed, '/')) {
            $cleanSlash = preg_replace('/\s*\/\s*/', '/', $trimmed) ?? $trimmed;

            return strtoupper(trim($cleanSlash));
        }

        // For strings containing only digits and whitespace, collapse spaces
        if (preg_match('/^[\d\s]+$/', $trimmed)) {
            return preg_replace('/\s+/', '', $trimmed) ?? $trimmed;
        }

        // Malformed or mixed strings: keep characters intact for diagnostic clarity
        return strtoupper($trimmed);
    }

    /**
     * Check if the input conforms to canonical 9-digit AAUA syntax (YY FF DD NNN).
     */
    public function validateCanonical(string $raw): bool
    {
        $normalized = $this->normalize($raw);

        return preg_match('/^\d{9}$/', $normalized) === 1;
    }

    /**
     * Check if the input conforms to a recognized historical slash format.
     */
    public function validateHistoricalSlash(string $raw): bool
    {
        $normalized = $this->normalize($raw);

        return preg_match('/^(?:AAUA\/)?([A-Z]{2,6})\/(\d{2,4})\/(\d{2,})$/i', $normalized) === 1;
    }

    /**
     * Check if the input matches any recognized valid format (canonical 9-digit or historical slash).
     */
    public function validateFormat(string $raw): bool
    {
        return $this->validateCanonical($raw) || $this->validateHistoricalSlash($raw);
    }

    /**
     * Deconstruct a structurally valid matriculation number into its components without DB lookups.
     *
     * @return array<string, mixed>|null
     */
    public function decodeComponents(string $raw): ?array
    {
        $normalized = $this->normalize($raw);

        if (preg_match('/^\d{9}$/', $normalized) === 1) {
            $yearCode = substr($normalized, 0, 2);
            $yearInt  = (int) $yearCode;
            $year     = $yearInt >= 70 ? (1900 + $yearInt) : (2000 + $yearInt);

            return [
                'type'                => MatricParseResult::FORMAT_CANONICAL_9,
                'admission_year'      => $year,
                'admission_year_code' => $yearCode,
                'faculty_code'        => substr($normalized, 2, 2),
                'department_code'     => substr($normalized, 4, 2),
                'student_sequence'    => substr($normalized, 6, 3),
            ];
        }

        if (preg_match('/^(?:AAUA\/)?([A-Z]{2,6})\/(\d{2,4})\/(\d{2,})$/i', $normalized, $matches) === 1) {
            $prefix   = strtoupper($matches[1]);
            $yearRaw  = $matches[2];
            $sequence = $matches[3];

            $year     = strlen($yearRaw) === 2 ? (2000 + (int) $yearRaw) : (int) $yearRaw;
            $yearCode = strlen($yearRaw) === 2 ? $yearRaw : substr($yearRaw, -2);

            return [
                'type'                => MatricParseResult::FORMAT_HISTORICAL_SLASH,
                'slash_prefix'        => $prefix,
                'slash_year'          => $year,
                'slash_sequence'      => $sequence,
                'admission_year'      => $year,
                'admission_year_code' => $yearCode,
                'student_sequence'    => $sequence,
            ];
        }

        return null;
    }

    /**
     * Resolve verified institutional faculty and department records from the database.
     * Never invents or fabricates faculty or department names.
     */
    public function resolveInstitutionalMapping(string $facultyCode, string $departmentCode): ?object
    {
        $map = $this->getDepartmentCodeMap();
        $key = $facultyCode . ':' . $departmentCode;

        return $map[$key] ?? null;
    }

    /**
     * Resolve department and faculty for historical slash prefixes based on database records.
     */
    public function resolveHistoricalSlashDepartment(string $prefix): ?object
    {
        $nameMap = $this->getDepartmentNameMap();
        $deptName = self::HISTORICAL_SLASH_PREFIXES[strtoupper($prefix)] ?? null;

        if (! $deptName) {
            return null;
        }

        return $nameMap[strtolower($deptName)] ?? null;
    }

    /**
     * Perform exact matching against official_students.
     *
     * @return object|null
     */
    public function resolveRegistryStudent(string $normalized): ?object
    {
        if ($normalized === '' || ! Schema::hasTable('official_students')) {
            return null;
        }

        $query = DB::table('official_students');
        if (Schema::hasColumn('official_students', 'normalized_matric_number')) {
            $query->where(function ($q) use ($normalized) {
                $q->where('normalized_matric_number', $normalized)
                    ->orWhere('matric_number', $normalized);
            });
        } else {
            $query->where('matric_number', $normalized);
        }

        return $query->first();
    }

    /**
     * Parse, validate, decode, and resolve a matriculation number.
     */
    public function parse(?string $raw, bool $verifyRegistry = true): MatricParseResult
    {
        $original   = (string) ($raw ?? '');
        $normalized = $this->normalize($raw);

        $errors   = [];
        $warnings = [];

        // 1. Empty Input Handling
        if ($normalized === '') {
            return new MatricParseResult(
                original: $original,
                normalized: '',
                format: MatricParseResult::FORMAT_EMPTY,
                is_valid_structure: false,
                errors: ['Matriculation number cannot be empty.']
            );
        }

        // 2. Canonical 9-Digit AAUA Syntax (YY FF DD NNN)
        if (preg_match('/^\d{9}$/', $normalized) === 1) {
            $yearCode = substr($normalized, 0, 2);
            $yearInt  = (int) $yearCode;
            $year     = $yearInt >= 70 ? (1900 + $yearInt) : (2000 + $yearInt);

            $facultyCode    = substr($normalized, 2, 2);
            $departmentCode = substr($normalized, 4, 2);
            $sequence       = substr($normalized, 6, 3);

            $canonicalDept = $this->resolveInstitutionalMapping($facultyCode, $departmentCode);

            if ($canonicalDept) {
                $format                  = MatricParseResult::FORMAT_CANONICAL_9;
                $isInstitutionallyMapped = true;
                $facultyName             = $canonicalDept->faculty ?? null;
                $departmentName          = $canonicalDept->dept_name ?? null;
                $departmentId            = isset($canonicalDept->dept_id) ? (int) $canonicalDept->dept_id : null;
                $isAnomalous             = false;
                $anomalousNote           = null;
                $candidateFacultyName    = null;
                $candidateDeptName       = null;
                $candidateDeptId         = null;
            } else {
                // Canonical mapping not found: check if this matches the known inverted DD/FF mock data anomaly
                $invertedDept = $this->resolveInstitutionalMapping($departmentCode, $facultyCode);

                if ($invertedDept) {
                    $format                  = MatricParseResult::FORMAT_ANOMALOUS_INVERTED_CANDIDATE;
                    $isInstitutionallyMapped = false;
                    $facultyName             = null;
                    $departmentName          = null;
                    $departmentId            = null;
                    $isAnomalous             = true;
                    $anomalousNote           = sprintf(
                        'Possible inverted department/faculty codes: digits 3-4 (%s) match department code and digits 5-6 (%s) match faculty code for %s in %s. Canonical AAUA format requires YY FF DD NNN.',
                        $facultyCode,
                        $departmentCode,
                        $invertedDept->dept_name,
                        $invertedDept->faculty
                    );
                    $candidateFacultyName    = $invertedDept->faculty ?? null;
                    $candidateDeptName       = $invertedDept->dept_name ?? null;
                    $candidateDeptId         = isset($invertedDept->dept_id) ? (int) $invertedDept->dept_id : null;
                    $warnings[]              = sprintf(
                        'Matriculation number matches the known inverted DD/FF mock data pattern for %s.',
                        $invertedDept->dept_name
                    );
                } else {
                    $format                  = MatricParseResult::FORMAT_CANONICAL_9;
                    $isInstitutionallyMapped = false;
                    $facultyName             = null;
                    $departmentName          = null;
                    $departmentId            = null;
                    $isAnomalous             = false;
                    $anomalousNote           = null;
                    $candidateFacultyName    = null;
                    $candidateDeptName       = null;
                    $candidateDeptId         = null;
                    $warnings[]              = sprintf(
                        'Unmapped institutional codes: Faculty %s, Department %s.',
                        $facultyCode,
                        $departmentCode
                    );
                }
            }

            [$registryVerified, $registryStatus, $officialStudent, $hasDuplicates] = $this->checkRegistryMatch(
                $normalized,
                $verifyRegistry,
                $warnings
            );

            return new MatricParseResult(
                original: $original,
                normalized: $normalized,
                format: $format,
                is_valid_structure: true,
                admission_year: $year,
                admission_year_code: $yearCode,
                faculty_code: $facultyCode,
                department_code: $departmentCode,
                student_sequence: $sequence,
                faculty_name: $facultyName,
                department_name: $departmentName,
                department_id: $departmentId,
                is_institutionally_mapped: $isInstitutionallyMapped,
                is_anomalous_candidate: $isAnomalous,
                anomalous_note: $anomalousNote,
                candidate_faculty_name: $candidateFacultyName,
                candidate_department_name: $candidateDeptName,
                candidate_department_id: $candidateDeptId,
                registry_verified: $registryVerified,
                registry_status: $registryStatus,
                official_student: $officialStudent,
                has_duplicate_registry_records: $hasDuplicates,
                errors: $errors,
                warnings: $warnings
            );
        }

        // 3. Historical Slash Format (PREFIX/YEAR/SEQUENCE or AAUA/PREFIX/YEAR/SEQUENCE)
        if (preg_match('/^(?:AAUA\/)?([A-Z]{2,6})\/(\d{2,4})\/(\d{2,})$/i', $normalized, $matches) === 1) {
            $prefix   = strtoupper($matches[1]);
            $yearRaw  = $matches[2];
            $sequence = $matches[3];

            $year     = strlen($yearRaw) === 2 ? (2000 + (int) $yearRaw) : (int) $yearRaw;
            $yearCode = strlen($yearRaw) === 2 ? $yearRaw : substr($yearRaw, -2);

            $slashDept = $this->resolveHistoricalSlashDepartment($prefix);

            $facultyName             = $slashDept->faculty ?? null;
            $departmentName          = $slashDept->dept_name ?? null;
            $departmentId            = isset($slashDept->dept_id) ? (int) $slashDept->dept_id : null;
            $isInstitutionallyMapped = $slashDept !== null;

            $warnings[] = sprintf('Historical slash format detected (department prefix %s).', $prefix);

            [$registryVerified, $registryStatus, $officialStudent, $hasDuplicates] = $this->checkRegistryMatch(
                $normalized,
                $verifyRegistry,
                $warnings
            );

            return new MatricParseResult(
                original: $original,
                normalized: $normalized,
                format: MatricParseResult::FORMAT_HISTORICAL_SLASH,
                is_valid_structure: true,
                admission_year: $year,
                admission_year_code: $yearCode,
                faculty_code: null,
                department_code: null,
                student_sequence: $sequence,
                slash_prefix: $prefix,
                slash_year: $year,
                slash_sequence: $sequence,
                faculty_name: $facultyName,
                department_name: $departmentName,
                department_id: $departmentId,
                is_institutionally_mapped: $isInstitutionallyMapped,
                is_anomalous_candidate: false,
                registry_verified: $registryVerified,
                registry_status: $registryStatus,
                official_student: $officialStudent,
                has_duplicate_registry_records: $hasDuplicates,
                errors: $errors,
                warnings: $warnings
            );
        }

        // 4. Malformed Input (Wrong length, non-numeric in digits, unsupported patterns)
        if (preg_match('/^\d+$/', $normalized) === 1) {
            $errors[] = sprintf(
                'Invalid length: matriculation number has %d digits (expected 9 digits for canonical AAUA format).',
                strlen($normalized)
            );
        } else {
            $errors[] = 'Malformed format: does not match canonical 9-digit (YYFFDDNNN) or historical slash formats.';
        }

        // Still check registry in case legacy/custom identifiers exist
        [$registryVerified, $registryStatus, $officialStudent, $hasDuplicates] = $this->checkRegistryMatch(
            $normalized,
            $verifyRegistry,
            $warnings
        );

        return new MatricParseResult(
            original: $original,
            normalized: $normalized,
            format: MatricParseResult::FORMAT_MALFORMED,
            is_valid_structure: false,
            registry_verified: $registryVerified,
            registry_status: $registryStatus,
            official_student: $officialStudent,
            has_duplicate_registry_records: $hasDuplicates,
            errors: $errors,
            warnings: $warnings
        );
    }

    /**
     * Batch parse an array of matriculation numbers efficiently.
     *
     * Pre-fetches matching official_students records in a single query to prevent N+1 queries.
     *
     * @param array<int, string> $matrics
     * @return Collection<int, MatricParseResult>
     */
    public function batchParse(array $matrics, bool $verifyRegistry = true): Collection
    {
        $normalizedMap = [];
        foreach ($matrics as $index => $raw) {
            $normalizedMap[$index] = $this->normalize($raw);
        }

        $registryRecords = collect();
        if ($verifyRegistry && Schema::hasTable('official_students')) {
            $distinctMatrics = array_values(array_filter(array_unique($normalizedMap), fn ($m) => $m !== ''));

            if (! empty($distinctMatrics)) {
                $query = DB::table('official_students');
                if (Schema::hasColumn('official_students', 'normalized_matric_number')) {
                    $query->where(function ($q) use ($distinctMatrics) {
                        $q->whereIn('normalized_matric_number', $distinctMatrics)
                            ->orWhereIn('matric_number', $distinctMatrics);
                    });
                } else {
                    $query->whereIn('matric_number', $distinctMatrics);
                }

                $registryRecords = $query->get()
                    ->groupBy(function ($item) {
                        return $item->normalized_matric_number ?? $item->matric_number;
                    });
            }
        }

        $results = collect();
        foreach ($matrics as $index => $raw) {
            $normalized = $normalizedMap[$index];

            // If registry pre-fetch is available, resolve using the pre-fetched group
            if ($verifyRegistry && $normalized !== '') {
                $matches = $registryRecords->get($normalized) ?? collect();
                $result  = $this->parseWithPrefetchedRegistry($raw, $normalized, $matches);
            } else {
                $result = $this->parse($raw, $verifyRegistry);
            }

            $results->push($result);
        }

        return $results;
    }

    /**
     * Internal helper to parse with pre-fetched registry records for high performance.
     */
    private function parseWithPrefetchedRegistry(string $raw, string $normalized, Collection $matches): MatricParseResult
    {
        // Parse without registry check first, then attach pre-fetched registry details
        $base = $this->parse($raw, verifyRegistry: false);

        $hasDuplicates = $matches->count() > 1;
        $officialStudent = $matches->first();
        $registryVerified = $officialStudent !== null;
        $registryStatus = $officialStudent ? strtolower((string) $officialStudent->status) : null;

        $warnings = $base->warnings;
        if ($hasDuplicates) {
            $warnings[] = sprintf(
                'Multiple registry records found for matric number %s (duplicate count: %d).',
                $normalized,
                $matches->count()
            );
        }

        return new MatricParseResult(
            original: $base->original,
            normalized: $base->normalized,
            format: $base->format,
            is_valid_structure: $base->is_valid_structure,
            admission_year: $base->admission_year,
            admission_year_code: $base->admission_year_code,
            faculty_code: $base->faculty_code,
            department_code: $base->department_code,
            student_sequence: $base->student_sequence,
            slash_prefix: $base->slash_prefix,
            slash_year: $base->slash_year,
            slash_sequence: $base->slash_sequence,
            faculty_name: $base->faculty_name,
            department_name: $base->department_name,
            department_id: $base->department_id,
            is_institutionally_mapped: $base->is_institutionally_mapped,
            is_anomalous_candidate: $base->is_anomalous_candidate,
            anomalous_note: $base->anomalous_note,
            candidate_faculty_name: $base->candidate_faculty_name,
            candidate_department_name: $base->candidate_department_name,
            candidate_department_id: $base->candidate_department_id,
            registry_verified: $registryVerified,
            registry_status: $registryStatus,
            official_student: $officialStudent,
            has_duplicate_registry_records: $hasDuplicates,
            errors: $base->errors,
            warnings: $warnings
        );
    }

    /**
     * Parse live format for backward compatibility with student onboarding.
     *
     * @throws InvalidArgumentException
     * @return array{matric_number: string, admission_year_code: string, session_code: string, faculty_code: string, department_code: string, student_number: string}
     */
    public function parseLiveFormat(?string $matricNumber): array
    {
        $normalized = $this->normalize($matricNumber);

        if ($normalized === '') {
            throw new InvalidArgumentException('Enter your matric number.');
        }

        if (preg_match('/^\d+$/', $normalized) !== 1) {
            throw new InvalidArgumentException('Matric number must contain numbers only.');
        }

        if (strlen($normalized) !== 9) {
            throw new InvalidArgumentException('Matric number must be exactly 9 digits, for example 220404008.');
        }

        return [
            'matric_number'       => $normalized,
            'admission_year_code' => substr($normalized, 0, 2),
            'session_code'        => substr($normalized, 0, 2), // backwards compatible alias
            'faculty_code'        => substr($normalized, 2, 2),
            'department_code'     => substr($normalized, 4, 2),
            'student_number'      => substr($normalized, 6, 3),
        ];
    }

    /**
     * Clear cached department and code dictionaries.
     */
    public function clearCache(): void
    {
        $this->departmentCodeCache = null;
        $this->departmentNameCache = null;
    }

    /**
     * Check official_students for exact matches and handle potential duplicate records.
     *
     * @return array{0: bool, 1: ?string, 2: ?object, 3: bool}
     */
    private function checkRegistryMatch(string $normalized, bool $verifyRegistry, array &$warnings): array
    {
        if (! $verifyRegistry || ! Schema::hasTable('official_students')) {
            return [false, null, null, false];
        }

        $query = DB::table('official_students');
        if (Schema::hasColumn('official_students', 'normalized_matric_number')) {
            $query->where(function ($q) use ($normalized) {
                $q->where('normalized_matric_number', $normalized)
                    ->orWhere('matric_number', $normalized);
            });
        } else {
            $query->where('matric_number', $normalized);
        }

        $records = $query->get();

        if ($records->count() > 1) {
            $officialStudent = $records->first();
            $warnings[]      = sprintf(
                'Multiple registry records found for matric number %s (duplicate count: %d).',
                $normalized,
                $records->count()
            );

            return [true, strtolower((string) $officialStudent->status), $officialStudent, true];
        }

        if ($records->count() === 1) {
            $officialStudent = $records->first();

            return [true, strtolower((string) $officialStudent->status), $officialStudent, false];
        }

        return [false, null, null, false];
    }

    /**
     * Load department codes into memory for high-performance lookup.
     *
     * @return array<string, object>
     */
    private function getDepartmentCodeMap(): array
    {
        if ($this->departmentCodeCache !== null) {
            return $this->departmentCodeCache;
        }

        if (! Schema::hasTable('departments')) {
            return $this->departmentCodeCache = [];
        }

        $map = [];
        $records = DB::table('departments')
            ->whereNotNull('faculty_code')
            ->whereNotNull('department_code')
            ->get();

        foreach ($records as $record) {
            $key = (string) $record->faculty_code . ':' . (string) $record->department_code;
            $map[$key] = $record;
        }

        return $this->departmentCodeCache = $map;
    }

    /**
     * Load departments indexed by lowercase name.
     *
     * @return array<string, object>
     */
    private function getDepartmentNameMap(): array
    {
        if ($this->departmentNameCache !== null) {
            return $this->departmentNameCache;
        }

        if (! Schema::hasTable('departments')) {
            return $this->departmentNameCache = [];
        }

        $map = [];
        $records = DB::table('departments')->get();

        foreach ($records as $record) {
            $map[strtolower(trim((string) $record->dept_name))] = $record;
        }

        return $this->departmentNameCache = $map;
    }
}
