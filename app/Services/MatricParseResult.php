<?php

namespace App\Services;

class MatricParseResult
{
    public const FORMAT_CANONICAL_9 = 'CANONICAL_9';
    public const FORMAT_ANOMALOUS_INVERTED_CANDIDATE = 'ANOMALOUS_INVERTED_CANDIDATE';
    public const FORMAT_HISTORICAL_SLASH = 'HISTORICAL_SLASH';
    public const FORMAT_MALFORMED = 'MALFORMED';
    public const FORMAT_EMPTY = 'EMPTY';

    public const REGISTRY_ACTIVE = 'active';
    public const REGISTRY_INACTIVE = 'inactive';
    public const REGISTRY_NOT_FOUND = 'not_found';

    /**
     * @param array<int, string> $errors
     * @param array<int, string> $warnings
     */
    public function __construct(
        public readonly string $original,
        public readonly string $normalized,
        public readonly string $format,
        public readonly bool $is_valid_structure,
        public readonly ?int $admission_year = null,
        public readonly ?string $admission_year_code = null,
        public readonly ?string $faculty_code = null,
        public readonly ?string $department_code = null,
        public readonly ?string $student_sequence = null,
        public readonly ?string $slash_prefix = null,
        public readonly ?int $slash_year = null,
        public readonly ?string $slash_sequence = null,
        public readonly ?string $faculty_name = null,
        public readonly ?string $department_name = null,
        public readonly ?int $department_id = null,
        public readonly bool $is_institutionally_mapped = false,
        public readonly bool $is_anomalous_candidate = false,
        public readonly ?string $anomalous_note = null,
        public readonly ?string $candidate_faculty_name = null,
        public readonly ?string $candidate_department_name = null,
        public readonly ?int $candidate_department_id = null,
        public readonly bool $registry_verified = false,
        public readonly ?string $registry_status = null,
        public readonly ?object $official_student = null,
        public readonly bool $has_duplicate_registry_records = false,
        public readonly array $errors = [],
        public readonly array $warnings = [],
    ) {}

    public function isValidStructure(): bool
    {
        return $this->is_valid_structure;
    }

    public function isCanonical(): bool
    {
        return $this->format === self::FORMAT_CANONICAL_9;
    }

    public function isHistoricalSlash(): bool
    {
        return $this->format === self::FORMAT_HISTORICAL_SLASH;
    }

    public function isAnomalousCandidate(): bool
    {
        return $this->is_anomalous_candidate;
    }

    public function isMapped(): bool
    {
        return $this->is_institutionally_mapped;
    }

    public function isRegistryVerified(): bool
    {
        return $this->registry_verified;
    }

    public function isActiveInRegistry(): bool
    {
        return $this->registry_verified && $this->registry_status === self::REGISTRY_ACTIVE;
    }

    public function isInactiveInRegistry(): bool
    {
        return $this->registry_verified && $this->registry_status === self::REGISTRY_INACTIVE;
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    /**
     * Convert the parse result to an associative array for logging, serialization, or JSON output.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'original'                       => $this->original,
            'normalized'                     => $this->normalized,
            'format'                         => $this->format,
            'is_valid_structure'             => $this->is_valid_structure,
            'admission_year'                 => $this->admission_year,
            'admission_year_code'            => $this->admission_year_code,
            'faculty_code'                   => $this->faculty_code,
            'department_code'                => $this->department_code,
            'student_sequence'               => $this->student_sequence,
            'slash_prefix'                   => $this->slash_prefix,
            'slash_year'                     => $this->slash_year,
            'slash_sequence'                 => $this->slash_sequence,
            'faculty_name'                   => $this->faculty_name,
            'department_name'                => $this->department_name,
            'department_id'                  => $this->department_id,
            'is_institutionally_mapped'      => $this->is_institutionally_mapped,
            'is_anomalous_candidate'         => $this->is_anomalous_candidate,
            'anomalous_note'                 => $this->anomalous_note,
            'candidate_faculty_name'         => $this->candidate_faculty_name,
            'candidate_department_name'      => $this->candidate_department_name,
            'candidate_department_id'        => $this->candidate_department_id,
            'registry_verified'              => $this->registry_verified,
            'registry_status'                => $this->registry_status,
            'has_duplicate_registry_records' => $this->has_duplicate_registry_records,
            'official_student'               => $this->official_student ? (array) $this->official_student : null,
            'errors'                         => $this->errors,
            'warnings'                       => $this->warnings,
        ];
    }
}
