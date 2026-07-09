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

    /** Fields that trigger a conflict notice when they change on re-import */
    private const CONFLICT_FIELDS = ['full_name', 'department', 'faculty', 'level'];

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

            $totalRows    = 0;
            $importedRows = 0;
            $skippedRows  = 0;
            $failedRows   = 0;
            $newRows      = 0;
            $updatedRows  = 0;
            $errors       = [];
            $conflicts    = [];

            DB::transaction(function () use (
                $handle,
                $columnIndexes,
                &$totalRows,
                &$importedRows,
                &$skippedRows,
                &$failedRows,
                &$newRows,
                &$updatedRows,
                &$errors,
                &$conflicts
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
                            $errors[] = ['row' => $totalRows + 1, 'reason' => $validationError, 'data' => $record];
                            continue;
                        }

                        // Detect conflicts with existing record before updating
                        $existing = OfficialStudent::where('matric_number', $record['matric_number'])->first();

                        if ($existing) {
                            $changes = [];
                            foreach (self::CONFLICT_FIELDS as $field) {
                                $oldVal = trim((string) ($existing->{$field} ?? ''));
                                $newVal = trim((string) ($record[$field] ?? ''));
                                if ($newVal !== '' && strtolower($oldVal) !== strtolower($newVal)) {
                                    $changes[$field] = ['from' => $oldVal, 'to' => $newVal];
                                }
                            }

                            if ($changes) {
                                $conflicts[] = [
                                    'matric' => $record['matric_number'],
                                    'name'   => $record['full_name'],
                                    'fields' => $changes,
                                ];
                            }

                            $updatedRows++;
                        } else {
                            $newRows++;
                        }

                        OfficialStudent::updateOrCreate(
                            ['matric_number' => $record['matric_number']],
                            $record
                        );

                        // Propagate name/dept/faculty/level changes to registered students too
                        if ($existing && ! empty($changes)) {
                            $this->syncRegisteredStudent($record);
                        }

                        $importedRows++;
                    } catch (\Throwable $exception) {
                        $skippedRows++;
                        $failedRows++;
                        $errors[] = ['row' => $totalRows + 1, 'reason' => $exception->getMessage(), 'data' => $record ?? []];
                    }
                }
            });

            return StudentRegistryImport::create([
                'uploaded_by'       => $uploadedBy,
                'original_filename' => $file->getClientOriginalName(),
                'total_rows'        => $totalRows,
                'imported_rows'     => $importedRows,
                'skipped_rows'      => $skippedRows,
                'failed_rows'       => $failedRows,
                'error_summary'     => [
                    'errors'        => array_slice($errors, 0, 25),
                    'conflicts'     => array_slice($conflicts, 0, 50),
                    'new_records'   => $newRows,
                    'updated_records' => $updatedRows,
                    'conflict_count'  => count($conflicts),
                ],
            ]);
        } finally {
            fclose($handle);
        }
    }

    /** Push name/department/faculty/level changes from official_students → students table */
    private function syncRegisteredStudent(array $record): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('students')) {
            return;
        }

        $dept = DB::table('departments')
            ->whereRaw('LOWER(dept_name) = ?', [strtolower($record['department'])])
            ->first();

        $updates = ['full_name' => $record['full_name'], 'level' => $record['level'], 'updated_at' => now()];
        if ($dept) {
            $updates['department_id'] = $dept->dept_id;
        }

        DB::table('students')
            ->where('matric_no', $record['matric_number'])
            ->update($updates);
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
