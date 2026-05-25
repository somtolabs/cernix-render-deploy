<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $activeSession = null;
        $activeExaminerCount = 0;
        $mockStudentCount = 0;

        try {
            DB::connection()->getPdo();
            $database = 'connected';
        } catch (\Throwable) {
            $database = 'error';
        }

        $sessionActive = false;
        try {
            $activeSession = DB::table('exam_sessions')
                ->where('is_active', true)
                ->select('session_id', 'semester', 'academic_year')
                ->first();

            $sessionActive = (bool) $activeSession;
            $activeExaminerCount = DB::table('examiners')->where('is_active', true)->count();
            $mockStudentCount = DB::table('mock_sis')->count();
        } catch (\Throwable) {
            // tables may not exist in a fresh install
        }

        return response()->json([
            'status'                => $database === 'connected' ? 'ok' : 'degraded',
            'database'              => $database,
            'session_active'        => $sessionActive,
            'active_session_id'     => $activeSession?->session_id,
            'active_session_label'  => $activeSession
                ? trim(($activeSession->semester ?? '') . ' ' . ($activeSession->academic_year ?? ''))
                : null,
            'active_examiner_count' => $activeExaminerCount,
            'mock_student_count'    => $mockStudentCount,
            'timestamp'             => now()->toIso8601String(),
        ]);
    }
}
