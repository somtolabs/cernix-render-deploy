<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\CryptoService;
use App\Services\MockSISService;
use App\Services\RegistrationService;
use App\Services\RemitaService;
use App\Support\DepartmentFees;
use App\Support\MatricNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentWebController extends Controller
{
    public function index()
    {
        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        $departments = DB::table('departments')->orderBy('dept_name')->get();
        $feeMap = DepartmentFees::configuredFees();
        $faculties = $departments->pluck('faculty')->filter()->unique()->values();
        if ($faculties->isEmpty()) {
            $faculties = collect([DepartmentFees::FACULTY]);
        }

        return view('student.register', compact('session', 'departments', 'feeMap', 'faculties'));
    }

    public function register(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'faculty' => 'nullable|string|max:100',
            'department_id' => 'required|integer|exists:departments,dept_id',
            'level' => 'required|string|in:100,200,300,400',
            'student_number' => ['required', 'string', 'regex:/^\d{3}$/'],
            'rrr_number' => ['required', 'string', 'max:50'],
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

            $this->confirmDemoStudentIfAllowed($data, $department);
            $this->validateSelectedIdentity($data);
            $expectedAmount = DepartmentFees::amountForDepartment($department->dept_name ?? null);

            if ($expectedAmount <= 0) {
                throw new \RuntimeException('School fee is not configured for the selected department.');
            }

            $cryptoService = new CryptoService();
            $regService = new RegistrationService(
                new MockSISService(),
                new class extends RemitaService {
                    public function __construct() { parent::__construct(new \GuzzleHttp\Client()); }
                    public function verifyPayment(string $rrrNumber, float $expectedAmount): array {
                        $rrrNumber = strtoupper(trim($rrrNumber));

                        if (\App\Support\DepartmentFees::startsWithTestPrefix($rrrNumber)) {
                            if (! \App\Support\DepartmentFees::isDemoRrr($rrrNumber)) {
                                throw new \RuntimeException('Use a valid demo RRR starting with TEST-, for example TEST-0001 or TEST-DEMO.');
                            }

                            if (! \App\Support\DepartmentFees::isDemoMode()) {
                                throw new \RuntimeException('Test RRR values are only allowed in demo mode.');
                            }

                            $demoAmount = $expectedAmount;

                            return [
                                'status' => 'Verified Demo Payment',
                                'amount' => (string) $demoAmount,
                                'RRR' => $rrrNumber,
                                'payment_type' => 'School Fees',
                                'payment_source' => 'demo',
                            ];
                        }

                        if (\App\Support\DepartmentFees::isDemoMode()) {
                            throw new \RuntimeException('Use a valid demo RRR starting with TEST-, for example TEST-0001 or TEST-DEMO.');
                        }

                        return ['status' => 'Payment Successful', 'amount' => (string) $expectedAmount, 'RRR' => $rrrNumber];
                    }
                },
                $cryptoService
            );

            $result = $regService->registerStudent([
                'matric_no' => $data['matric_no'],
                'full_name' => '',
                'rrr_number' => $data['rrr_number'],
                'expected_amount' => $expectedAmount,
                'session_id' => (int) $session->session_id,
            ]);

            $request->session()->put('student_matric_no', $data['matric_no']);
            $request->session()->put('student_session_id', (int) $session->session_id);

            app(AuditService::class)->logAction(
                $data['matric_no'],
                'student',
                'student.registered',
                ['token_id' => $result['data']['token_id'], 'session_id' => $session->session_id]
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
                    'token_id' => $result['data']['token_id'],
                ],
            ]);
        } catch (\Throwable $e) {
            if (! $request->expectsJson()) {
                return back()->withErrors(['registration' => $e->getMessage()])->withInput();
            }

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
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

        $rrrNumber = strtoupper(trim((string) ($data['rrr_number'] ?? '')));

        if (DepartmentFees::startsWithTestPrefix($rrrNumber) && ! DepartmentFees::isDemoRrr($rrrNumber)) {
            throw new \RuntimeException('Use a valid demo RRR starting with TEST-, for example TEST-0001 or TEST-DEMO.');
        }

        if (DepartmentFees::isDemoRrr($rrrNumber) && ! DepartmentFees::isDemoMode()) {
            throw new \RuntimeException('Test RRR values are only allowed in demo mode.');
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

    private function confirmDemoStudentIfAllowed(array $data, object $department): void
    {
        $rrrNumber = strtoupper(trim((string) ($data['rrr_number'] ?? '')));

        if (! DepartmentFees::startsWithTestPrefix($rrrNumber)) {
            return;
        }

        if (! DepartmentFees::isDemoRrr($rrrNumber)) {
            throw new \RuntimeException('Use a valid demo RRR starting with TEST-, for example TEST-0001 or TEST-DEMO.');
        }

        if (! DepartmentFees::isDemoMode()) {
            throw new \RuntimeException('Test RRR values are only allowed in demo mode.');
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

}
