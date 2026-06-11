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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

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

        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        if (! $session) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No active exam session found.',
            ], 422);
        }

        $matricNo = (string) $request->input('matric_no', '');
        $paymentQuery = DB::table('payment_records')->where('student_id', $matricNo);
        if (Schema::hasColumn('payment_records', 'session_id')) {
            $paymentQuery->where(function ($query) use ($session) {
                $query->where('session_id', $session->session_id)
                    ->orWhereNull('session_id');
            });
        }
        $hasVerifiedPayment = $matricNo !== '' && $paymentQuery->exists();

        $data = $request->validate([
            'matric_no'  => 'required|string|max:50',
            'rrr_number' => [$hasVerifiedPayment ? 'nullable' : 'required', 'string', 'max:50'],
            'timetable_id' => 'required|integer',
        ]);

        try {
            $student = DB::table('students')
                ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
                ->where('students.matric_no', $data['matric_no'])
                ->where('students.session_id', $session->session_id)
                ->select('students.*', 'departments.dept_name')
                ->first();
            if (! $student) {
                throw new \RuntimeException('Register your student profile before generating a course QR pass.');
            }

            $timetableId = (int) $data['timetable_id'];

            $result = $this->examPassService->generate(
                $data['matric_no'],
                (int) $session->session_id,
                $timetableId,
                $data['rrr_number'] ?? null,
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
                'message' => $hasVerifiedPayment
                    ? 'Verified session payment reused and course QR pass generated.'
                    : 'Payment verified and course QR pass generated.',
                'data'    => $result,
            ]);

        } catch (RuntimeException $e) {
            $message = str_contains(strtoupper($e->getMessage()), 'SQLSTATE')
                ? 'The course QR pass could not be generated right now. Please try again shortly.'
                : $e->getMessage();

            Log::warning('Student exam pass API request failed.', [
                'matric_no' => $matricNo,
                'session_id' => $session->session_id,
                'reason' => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => $message,
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Student exam pass API request failed unexpectedly.', [
                'matric_no' => $matricNo,
                'session_id' => $session->session_id,
                'exception' => $e::class,
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'The course QR pass could not be generated right now. Please try again shortly.',
            ], 422);
        }
    }
}
