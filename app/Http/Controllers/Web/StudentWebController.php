<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StudentWebController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {}

    public function index()
    {
        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        return view('student.register', compact('session'));
    }

    public function register(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'matric_no' => ['required_without:matric_number', 'nullable', 'string', 'max:50'],
            'matric_number' => ['required_without:matric_no', 'nullable', 'string', 'max:50'],
            'passport_photo' => ['required', 'image', 'mimes:jpg,jpeg', 'max:2048'],
        ]);

        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        if (! $session) {
            return response()->json(['success' => false, 'message' => 'No active exam session found.'], 422);
        }

        try {
            $data['matric_no'] = strtoupper(trim((string) ($data['matric_no'] ?? $data['matric_number'])));
            $this->assertOfficialStudentCanRegister($data['matric_no']);
            $data['photo_path'] = $this->storePassportPhoto($request, $data['matric_no']);

            $result = $this->registrationService->registerStudent([
                'matric_no' => $data['matric_no'],
                'session_id' => (int) $session->session_id,
                'photo_path' => $data['photo_path'],
            ]);

            $request->session()->put('student_matric_no', $data['matric_no']);
            $request->session()->put('student_session_id', (int) $session->session_id);

            app(AuditService::class)->logAction(
                $data['matric_no'],
                'student',
                'student.registered',
                [
                    'session_id' => $session->session_id,
                    'photo_status' => $result['data']['photo_status'] ?? 'pending_admin_approval',
                ]
            );

            if (! $request->expectsJson()) {
                return redirect()->route('student.dashboard');
            }

            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Your profile is waiting for admin approval.',
                'redirect_url' => route('student.dashboard'),
                'data' => [
                    'matric_no' => $data['matric_no'],
                    'photo_status' => $result['data']['photo_status'] ?? 'pending_admin_approval',
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logRegistrationFailure($e, $data, $session);
            $message = $this->registrationErrorMessage($e);

            if (! $request->expectsJson()) {
                return back()->withErrors(['registration' => $message])->withInput();
            }

            return response()->json(['success' => false, 'message' => $message], 422);
        }
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

        return 'Registration could not be completed right now. Please check your details and try again.';
    }

    private function logRegistrationFailure(\Throwable $exception, array $data, object $session): void
    {
        $matricNo = (string) ($data['matric_no'] ?? '');

        try {
            $context = [
                'exception_class' => $exception::class,
                'database_driver' => DB::getDriverName(),
                'active_session_exists' => (bool) $session,
                'official_student_exists' => $matricNo !== ''
                    && Schema::hasTable('official_students')
                    && DB::table('official_students')->where('matric_number', $matricNo)->exists(),
                'schema' => [
                    'departments' => Schema::hasTable('departments'),
                    'exam_sessions' => Schema::hasTable('exam_sessions'),
                    'official_students' => Schema::hasTable('official_students'),
                    'students' => Schema::hasTable('students'),
                    'students_session_id' => Schema::hasColumn('students', 'session_id'),
                    'students_level' => Schema::hasColumn('students', 'level'),
                    'students_photo_status' => Schema::hasColumn('students', 'photo_status'),
                ],
            ];
        } catch (\Throwable $diagnosticException) {
            $context = [
                'exception_class' => $exception::class,
                'diagnostic_exception_class' => $diagnosticException::class,
            ];
        }

        Log::warning('Student registration failed.', $context);
    }

    private function storePassportPhoto(Request $request, string $matricNo): string
    {
        $file = $request->file('passport_photo');
        $directory = public_path('photos/student-submissions');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = Str::slug(str_replace('/', '-', $matricNo))
            . '-'
            . now()->format('YmdHis')
            . '-'
            . Str::random(8)
            . '.jpg';

        file_put_contents($directory . DIRECTORY_SEPARATOR . $filename, file_get_contents($file->getRealPath()));

        return 'photos/student-submissions/' . $filename;
    }

    private function assertOfficialStudentCanRegister(string $matricNo): void
    {
        $officialStudent = DB::table('official_students')
            ->where('matric_number', $matricNo)
            ->first();

        if (! $officialStudent) {
            throw new \RuntimeException('your matric number was not found in the official student list. please contact the admin or exam officer.');
        }

        if (strtolower((string) $officialStudent->status) !== 'active') {
            throw new \RuntimeException('This matric number is not active on the official student list. Please contact the admin or exam officer.');
        }
    }
}
