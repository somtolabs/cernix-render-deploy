<?php

namespace App\Services;

use App\Models\OfficialStudent;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OfficialStudentProtectionService
{
    /**
     * Map of relations and tables to inspect for student activity and dependencies.
     */
    public const RELATION_TABLES = [
        'portal_account'     => ['table' => 'students', 'column' => 'matric_no', 'label' => 'Portal Account'],
        'assessment_roster'  => ['table' => 'timetable_students', 'column' => 'matric_no', 'label' => 'Assessment Roster'],
        'attendance_record'  => ['table' => 'attendance_records', 'column' => 'matric_no', 'label' => 'Attendance Record'],
        'payment_record'     => ['table' => 'payment_records', 'column' => 'student_id', 'label' => 'Payment Record'],
        'qr_token'           => ['table' => 'qr_tokens', 'column' => 'student_id', 'label' => 'Exam Pass / QR Token'],
        'photo_request'      => ['table' => 'profile_photo_change_requests', 'column' => 'matric_no', 'label' => 'Photo Request'],
        'assisted_checkin'   => ['table' => 'assisted_checkin_logs', 'column' => 'matric_no', 'label' => 'Assisted Check-in'],
    ];

    /**
     * Get list of protection reasons for an individual official student.
     * Returns an empty array if the student has zero dependencies and can safely be removed.
     *
     * @return array<string, string> Keyed by relation key, value is human-readable label
     */
    public function getRecordProtectionReasons(OfficialStudent|string $student): array
    {
        $matric = $student instanceof OfficialStudent ? $student->matric_number : (string) $student;
        $normMatric = $student instanceof OfficialStudent ? $student->normalized_matric_number : null;

        if (empty($matric)) {
            return [];
        }

        $reasons = [];

        foreach (self::RELATION_TABLES as $key => $info) {
            $table = $info['table'];
            $col   = $info['column'];

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $col)) {
                continue;
            }

            $query = DB::table($table)->where(function ($q) use ($col, $matric, $normMatric) {
                $q->where($col, $matric);
                if ($normMatric && $normMatric !== $matric) {
                    $q->orWhere($col, $normMatric);
                }
            });

            if ($query->exists()) {
                $reasons[$key] = $info['label'];
            }
        }

        return $reasons;
    }

    /**
     * Determine if an individual official student is protected from deletion.
     */
    public function isProtected(OfficialStudent|string $student): bool
    {
        return ! empty($this->getRecordProtectionReasons($student));
    }

    /**
     * Provide a comprehensive review of the entire registry, breaking down eligible unlinked
     * records vs protected records and listing exact counts per relation.
     *
     * @return array{
     *     total: int,
     *     unlinked_count: int,
     *     protected_count: int,
     *     protection_reasons: array<string, int>,
     *     reason_labels: array<string, string>
     * }
     */
    public function getProtectionReview(): array
    {
        if (! Schema::hasTable('official_students')) {
            return [
                'total'              => 0,
                'unlinked_count'     => 0,
                'protected_count'    => 0,
                'protection_reasons' => [],
                'reason_labels'      => [],
            ];
        }

        $total = DB::table('official_students')->count();
        if ($total === 0) {
            return [
                'total'              => 0,
                'unlinked_count'     => 0,
                'protected_count'    => 0,
                'protection_reasons' => [],
                'reason_labels'      => [],
            ];
        }

        $reasonCounts = [];
        $reasonLabels = [];

        foreach (self::RELATION_TABLES as $key => $info) {
            $table = $info['table'];
            $col   = $info['column'];
            $reasonLabels[$key] = $info['label'];

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $col)) {
                $reasonCounts[$key] = 0;
                continue;
            }

            // Count distinct official students linked in this table
            $count = DB::table('official_students as os')
                ->whereExists(function ($q) use ($table, $col) {
                    $q->select(DB::raw(1))
                        ->from($table)
                        ->where(function ($inner) use ($table, $col) {
                            $inner->whereColumn("{$table}.{$col}", 'os.matric_number');
                            if (Schema::hasColumn('official_students', 'normalized_matric_number')) {
                                $inner->orWhere(function ($orQ) use ($table, $col) {
                                    $orQ->whereNotNull('os.normalized_matric_number')
                                        ->whereColumn("{$table}.{$col}", 'os.normalized_matric_number');
                                });
                            }
                        });
                })
                ->count();

            $reasonCounts[$key] = $count;
        }

        $unlinkedCount = $this->buildUnlinkedQuery()->count();
        $protectedCount = max(0, $total - $unlinkedCount);

        return [
            'total'              => $total,
            'unlinked_count'     => $unlinkedCount,
            'protected_count'    => $protectedCount,
            'protection_reasons' => $reasonCounts,
            'reason_labels'      => $reasonLabels,
        ];
    }

    /**
     * Build the query for official students who have zero relations in all protected tables.
     */
    public function buildUnlinkedQuery(): Builder
    {
        $query = DB::table('official_students as os');

        foreach (self::RELATION_TABLES as $info) {
            $table = $info['table'];
            $col   = $info['column'];

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $col)) {
                continue;
            }

            $query->whereNotExists(function ($q) use ($table, $col) {
                $q->select(DB::raw(1))
                    ->from($table)
                    ->where(function ($inner) use ($table, $col) {
                        $inner->whereColumn("{$table}.{$col}", 'os.matric_number');
                        if (Schema::hasColumn('official_students', 'normalized_matric_number')) {
                            $inner->orWhere(function ($orQ) use ($table, $col) {
                                $orQ->whereNotNull('os.normalized_matric_number')
                                    ->whereColumn("{$table}.{$col}", 'os.normalized_matric_number');
                            });
                        }
                    });
            });
        }

        return $query;
    }

    /**
     * Safely delete all unlinked official students.
     * Guaranteed to never delete records with portal accounts, exam rosters, attendance, payments, or passes.
     *
     * @return int Number of deleted records
     */
    public function deleteUnlinkedStudents(): int
    {
        return DB::transaction(function () {
            $unlinkedIds = $this->buildUnlinkedQuery()->pluck('os.id')->all();

            if (empty($unlinkedIds)) {
                return 0;
            }

            return DB::table('official_students')
                ->whereIn('id', $unlinkedIds)
                ->delete();
        });
    }

    /**
     * Deactivate protected official students (set status = 'inactive') to safely restrict access
     * without deleting portal accounts or historical records.
     *
     * @return int Number of updated records
     */
    public function deactivateProtectedStudents(): int
    {
        return DB::transaction(function () {
            $unlinkedIds = $this->buildUnlinkedQuery()->pluck('os.id')->all();

            $query = DB::table('official_students')
                ->where('status', '!=', 'inactive');

            if (! empty($unlinkedIds)) {
                $query->whereNotIn('id', $unlinkedIds);
            }

            return $query->update(['status' => 'inactive']);
        });
    }

    /**
     * Deactivate all active official student records across the registry.
     *
     * @return int Number of updated records
     */
    public function deactivateAllStudents(): int
    {
        return DB::table('official_students')
            ->where('status', '!=', 'inactive')
            ->update(['status' => 'inactive']);
    }
}
