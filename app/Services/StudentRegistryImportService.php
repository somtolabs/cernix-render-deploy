<?php

namespace App\Services;

use App\Models\OfficialStudent;
use App\Models\StudentRegistryImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StudentRegistryImportService
{
    private const REQUIRED_COLUMNS = [
        'matric_number',
        'full_name',
        'department',
        'faculty',
        'level',
    ];

    private const OPTIONAL_COLUMNS = [
        'programme',
        'academic_session',
        'status',
    ];

    public function import(UploadedFile $file, ?string $uploadedBy): StudentRegistryImport
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw new RuntimeException('The uploaded CSV could not be opened.');
        }

        try {
            $header = fgetcsv($handle);
            if (! is_array($header)) {
                throw new RuntimeException('The uploaded CSV is empty.');
            }

            $header = array_map(fn ($value) => $this->normalizeHeader((string) $value), $header);
            $missing = array_values(array_diff(self::REQUIRED_COLUMNS, $header));
            if ($missing) {
                throw new RuntimeException('Missing required CSV columns: ' . implode(', ', $missing) . '.');
            }

            $allowedColumns = array_merge(self::REQUIRED_COLUMNS, self::OPTIONAL_COLUMNS);
            $columnIndexes = [];
            foreach ($header as $index => $column) {
                if (in_array($column, $allowedColumns, true)) {
                    $columnIndexes[$column] = $index;
                }
            }

            $totalRows = 0;
            $importedRows = 0;
            $skippedRows = 0;
            $failedRows = 0;
            $errors = [];

            DB::transaction(function () use (
                $handle,
                $columnIndexes,
                &$totalRows,
                &$importedRows,
                &$skippedRows,
                &$failedRows,
                &$errors
            ) {
                while (($row = fgetcsv($handle)) !== false) {
                    if ($row === [null] || count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                        continue;
                    }

                    $totalRows++;

                    try {
                        $record = $this->rowRecord($row, $columnIndexes);
                        $validationError = $this->validateRow($record);

                        if ($validationError !== null) {
                            $skippedRows++;
                            $failedRows++;
                            $errors[] = ['row' => $totalRows + 1, 'reason' => $validationError];
                            continue;
                        }

                        OfficialStudent::updateOrCreate(
                            ['matric_number' => $record['matric_number']],
                            $record
                        );

                        $importedRows++;
                    } catch (\Throwable $exception) {
                        $skippedRows++;
                        $failedRows++;
                        $errors[] = ['row' => $totalRows + 1, 'reason' => $exception->getMessage()];
                    }
                }
            });

            return StudentRegistryImport::create([
                'uploaded_by' => $uploadedBy,
                'original_filename' => $file->getClientOriginalName(),
                'total_rows' => $totalRows,
                'imported_rows' => $importedRows,
                'skipped_rows' => $skippedRows,
                'failed_rows' => $failedRows,
                'error_summary' => array_slice($errors, 0, 25),
            ]);
        } finally {
            fclose($handle);
        }
    }

    private function rowRecord(array $row, array $columnIndexes): array
    {
        $value = fn (string $column): ?string => array_key_exists($column, $columnIndexes)
            ? $this->trimValue($row[$columnIndexes[$column]] ?? null)
            : null;

        return [
            'matric_number' => strtoupper((string) $value('matric_number')),
            'full_name' => (string) $value('full_name'),
            'department' => (string) $value('department'),
            'faculty' => (string) $value('faculty'),
            'level' => (string) $value('level'),
            'programme' => $value('programme'),
            'academic_session' => $value('academic_session'),
            'status' => strtolower((string) ($value('status') ?: 'active')),
        ];
    }

    private function validateRow(array $record): ?string
    {
        foreach (self::REQUIRED_COLUMNS as $column) {
            if (($record[$column] ?? '') === '') {
                return "Missing required value: {$column}";
            }
        }

        if (! in_array($record['status'], ['active', 'inactive'], true)) {
            return 'Status must be active or inactive.';
        }

        return null;
    }

    private function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        return strtolower(trim($value));
    }

    private function trimValue(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
