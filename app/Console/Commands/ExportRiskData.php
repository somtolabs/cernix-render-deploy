<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportRiskData extends Command
{
    protected $signature = 'cernix:export-risk-data {--path= : Custom storage-relative output path}';

    protected $description = 'Export safe scan log data for the optional CERNIX Python Risk Analyzer';

    public function handle(): int
    {
        $path = $this->option('path') ?: 'risk-analysis/scan_logs.json';
        $latestPayments = DB::table('payment_records')
            ->select('student_id', DB::raw('MAX(verified_at) as verified_at'), DB::raw('MAX(amount_confirmed) as amount_confirmed'), DB::raw('MAX(rrr_number) as rrr_number'))
            ->groupBy('student_id');

        $rows = DB::table('verification_logs')
            ->leftJoin('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->leftJoin('students', 'qr_tokens.student_id', '=', 'students.matric_no')
            ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
            ->leftJoin('examiners', 'verification_logs.examiner_id', '=', 'examiners.examiner_id')
            ->leftJoinSub($latestPayments, 'latest_payments', fn ($join) => $join->on('students.matric_no', '=', 'latest_payments.student_id'))
            ->orderByDesc('verification_logs.timestamp')
            ->select([
                'students.matric_no',
                'students.full_name as student_name',
                'students.level',
                'departments.dept_name as department',
                'verification_logs.examiner_id',
                'examiners.full_name as examiner_name',
                'verification_logs.decision',
                'verification_logs.token_id',
                'verification_logs.device_fp',
                'verification_logs.ip_address',
                'verification_logs.timestamp',
                'qr_tokens.status as token_status',
                'qr_tokens.status as qr_status',
                'qr_tokens.issued_at',
                'qr_tokens.used_at',
                'latest_payments.rrr_number',
                'latest_payments.amount_confirmed',
                'latest_payments.verified_at',
            ])
            ->get()
            ->map(fn ($row) => [
                'student_id' => $row->matric_no,
                'matric_no' => $row->matric_no,
                'student_name' => $row->student_name,
                'department' => $row->department,
                'level' => $row->level,
                'examiner_id' => $row->examiner_id,
                'examiner_name' => $row->examiner_name,
                'decision' => $row->decision,
                'token_id' => $row->token_id,
                'token_status' => $row->token_status,
                'qr_status' => $row->qr_status,
                'device_fp' => $row->device_fp,
                'ip_address' => $row->ip_address,
                'timestamp' => $row->timestamp,
                'payment_status' => $row->verified_at ? 'verified' : 'unverified',
                'rrr_number' => $this->maskRrr($row->rrr_number),
                'amount_confirmed' => $row->amount_confirmed === null ? null : (float) $row->amount_confirmed,
                'course_code' => null,
                'course_title' => null,
            ])
            ->values()
            ->all();

        $fullPath = storage_path('app/' . ltrim($path, '/'));
        $directory = dirname($fullPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($fullPath, json_encode([
            'generated_at' => now()->toIso8601String(),
            'scan_logs' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info('Risk analysis export written to storage/app/' . $path);
        $this->line(count($rows) . ' scan log rows exported.');

        return self::SUCCESS;
    }

    private function maskRrr(?string $rrr): ?string
    {
        if (! $rrr) {
            return null;
        }

        $rrr = trim($rrr);

        if (str_starts_with(strtoupper($rrr), 'TEST-')) {
            return 'TEST-****';
        }

        $length = strlen($rrr);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', max(0, $length - 4)) . substr($rrr, -4);
    }
}
