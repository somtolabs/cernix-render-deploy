<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\ExamPassService;
use App\Support\DepartmentFees;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    public function __construct(
        private readonly ExamPassService $examPassService,
        private readonly AuditService $auditService,
    ) {}

    public function registerExam(Request $request): JsonResponse
    {
        if (Auth::guard('api')->user()?->role !== 'student') {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'matric_no'  => 'required|string|max:50',
            'rrr_number' => 'required|string|max:50',
            'timetable_id' => 'nullable|integer',
        ]);

        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        if (! $session) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No active exam session found.',
            ], 422);
        }

        try {
            $student = DB::table('students')
                ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
                ->where('students.matric_no', $data['matric_no'])
                ->where('students.session_id', $session->session_id)
                ->select('students.*', 'departments.dept_name')
                ->first();
            if (! $student) {
                throw new \RuntimeException('Register your student profile before generating an exam pass.');
            }

            $timetableId = (int) ($data['timetable_id'] ?? DB::table('timetables')
                ->where('exam_session_id', $session->session_id)
                ->where('department_id', $student->department_id)
                ->where('level', (string) $student->level)
                ->where('status', '!=', 'cancelled')
                ->orderBy('exam_date')
                ->orderBy('start_time')
                ->value('id'));
            if (! $timetableId) {
                throw new \RuntimeException('No assigned course is available for this student.');
            }

            $result = $this->examPassService->generate(
                $data['matric_no'],
                (int) $session->session_id,
                $timetableId,
                $data['rrr_number'],
                DepartmentFees::amountForDepartment($student->dept_name),
            );

            $this->auditService->logAction(
                $data['matric_no'],
                'student',
                'exam_pass.generated',
                ['token_id' => $result['token_id'], 'session_id' => $session->session_id, 'timetable_id' => $timetableId]
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Payment verified and exam pass generated.',
                'data'    => $result,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
