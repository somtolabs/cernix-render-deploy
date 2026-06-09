<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\MockSISService;
use App\Services\RegistrationService;
use App\Support\DepartmentFees;
use App\Support\MatricNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentWebController extends Controller
{
    public function index()
    {
        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        $departments = DB::table('departments')->orderBy('dept_name')->get();
        $faculties = $departments->pluck('faculty')->filter()->unique()->values();
        if ($faculties->isEmpty()) {
            $faculties = collect([DepartmentFees::FACULTY]);
        }

        return view('student.register', compact('session', 'departments', 'faculties'));
    }

    public function register(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'faculty' => 'nullable|string|max:100',
            'department_id' => 'required|integer|exists:departments,dept_id',
            'level' => 'required|string|in:100,200,300,400',
            'student_number' => ['required', 'string', 'regex:/^\d{3}$/'],
        ]);

        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        if (! $session) {
            return response()->json(['success' => false, 'message' => 'No active exam session found.'], 422);
        }

        try {
            $department = DB::table('departments')->where('dept_id', $data['department_id'])->first();
            $data['faculty'] = ($data['faculty'] ?? null) ?: ($department->faculty ?? DepartmentFees::FACULTY);
            $data['matric_no'] = MatricNumber::generate(
                (string) $data['level'],
                (string) $data['faculty'],
                (string) ($department->dept_name ?? ''),
                (string) $data['student_number']
            );

            if (DepartmentFees::isDemoMode() && ! MatricNumber::hasDemoPhoto((string) $data['student_number'])) {
                throw new \RuntimeException('Demo passport photo is only available for student numbers 001 to 014 right now.');
            }

            $this->ensureDemoStudentIfAllowed($data, $department);
            $this->validateSelectedIdentity($data);
            $isDemoSample = DepartmentFees::isDemoMode()
                && MatricNumber::hasDemoPhoto((string) $data['student_number']);
            $existingDemoStudent = $isDemoSample
                ? DB::table('students')
                    ->where('matric_no', $data['matric_no'])
                    ->where('session_id', $session->session_id)
                    ->exists()
                : false;

            if (! $existingDemoStudent) {
                $regService = new RegistrationService(new MockSISService());
                $regService->registerStudent([
                    'matric_no' => $data['matric_no'],
                    'session_id' => (int) $session->session_id,
                ]);
            }

            $request->session()->put('student_matric_no', $data['matric_no']);
            $request->session()->put('student_session_id', (int) $session->session_id);

            app(AuditService::class)->logAction(
                $data['matric_no'],
                'student',
                $existingDemoStudent ? 'student.demo_session_resumed' : 'student.registered',
                ['session_id' => $session->session_id]
            );

            if (! $request->expectsJson()) {
                return redirect()->route('student.dashboard');
            }

            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Opening your exam dashboard.',
                'redirect_url' => route('student.dashboard'),
                'data' => [
                    'matric_no' => $data['matric_no'],
                ],
            ]);
        } catch (\Throwable $e) {
            $message = $this->registrationErrorMessage($e);

            if (! $request->expectsJson()) {
                return back()->withErrors(['registration' => $message])->withInput();
            }

            return response()->json(['success' => false, 'message' => $message], 422);
        }
    }

    private function validateSelectedIdentity(array $data): void
    {
        $sis = (new MockSISService())->getStudentByMatric($data['matric_no']);
        $department = DB::table('departments')->where('dept_id', $data['department_id'])->first();

        if (! $department || strcasecmp((string) $department->dept_name, (string) $sis['department']) !== 0) {
            throw new \RuntimeException('Selected department does not match this matric number. Expected department: ' . ($sis['department'] ?? 'Not available') . '.');
        }

        if (strcasecmp((string) $department->faculty, (string) $data['faculty']) !== 0) {
            throw new \RuntimeException('Selected faculty does not match the selected department.');
        }

        if (($sis['level'] ?? null) && (string) $sis['level'] !== (string) $data['level']) {
            throw new \RuntimeException('Selected level does not match this matric number. Expected level: ' . $sis['level'] . '.');
        }

        if (($sis['photo_path'] ?? null) && DepartmentFees::isDemoMode()) {
            $expectedPhoto = MatricNumber::demoPhotoPath((string) $data['student_number']);
            if ((string) $sis['photo_path'] !== $expectedPhoto) {
                throw new \RuntimeException('Demo passport photo is only available for student numbers 001 to 014 right now.');
            }
        }

        if (preg_match('/^\d{9}$/', $data['matric_no']) === 1) {
            $facultyCode = substr($data['matric_no'], 2, 2);
            $departmentCode = substr($data['matric_no'], 4, 2);
            if (($department->department_code ?? null) && $departmentCode !== $department->department_code) {
                throw new \RuntimeException('Matric number department code does not match the selected department.');
            }
            if (($department->faculty_code ?? null) && $facultyCode !== $department->faculty_code) {
                throw new \RuntimeException('Matric number faculty code does not match the selected department.');
            }
        } elseif (preg_match('/^[A-Z]{3}\/\d{4}\/\d{3}$/i', $data['matric_no']) !== 1) {
            throw new \RuntimeException('Enter a valid 9-digit AAUA matric number or a legacy matric number.');
        }
    }

    private function ensureDemoStudentIfAllowed(array $data, object $department): void
    {
        if (! DepartmentFees::isDemoMode()) {
            return;
        }

        if (! MatricNumber::hasDemoPhoto((string) $data['student_number'])) {
            throw new \RuntimeException('Demo passport photo is only available for student numbers 001 to 014 right now.');
        }

        DB::table('mock_sis')->updateOrInsert(
            ['matric_no' => $data['matric_no']],
            [
                'full_name' => MatricNumber::demoName((string) $data['student_number']),
                'department' => $department->dept_name,
                'department_code' => $department->department_code ?? MatricNumber::DEPARTMENT_CODES[$department->dept_name] ?? null,
                'faculty_code' => $department->faculty_code ?? MatricNumber::FACULTY_CODES[$department->faculty] ?? null,
                'level' => (string) $data['level'],
                'photo_path' => MatricNumber::demoPhotoPath((string) $data['student_number']),
            ]
        );
    }

    private function registrationErrorMessage(\Throwable $exception): string
    {
        $message = $exception->getMessage();
        $containsInternalDetails = preg_match(
            '/SQLSTATE|database|table\s+\S+|column\s+\S+|stack trace|constraint/i',
            $message
        ) === 1;

        if (! $containsInternalDetails
            && ($exception instanceof \InvalidArgumentException || $exception instanceof \RuntimeException)) {
            return $exception->getMessage();
        }

        Log::error('Student registration failed.', [
            'exception' => $exception,
        ]);

        return 'Registration could not be completed. Please check your details and try again.';
    }

}
