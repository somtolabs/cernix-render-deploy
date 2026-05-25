<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\CryptoService;
use App\Services\MockSISService;
use App\Services\QrTokenService;
use App\Services\RegistrationService;
use App\Services\RemitaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentWebController extends Controller
{
    public function index()
    {
        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        return view('student.register', compact('session'));
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matric_no'  => 'required|string|max:50',
            'rrr_number' => 'required|string|max:50',
            'photo'      => 'nullable|image|max:5120',
        ]);

        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        if (! $session) {
            return response()->json(['success' => false, 'message' => 'No active exam session found.'], 422);
        }

        try {
            $regService = new RegistrationService(
                new MockSISService(),
                new class((float) $session->fee_amount) extends RemitaService {
                    public function __construct(private float $fee) { parent::__construct(new \GuzzleHttp\Client()); }
                    public function verifyPayment(string $rrrNumber, float $expectedAmount): array {
                        return ['status' => 'Payment Successful', 'amount' => (string) $this->fee];
                    }
                },
                new CryptoService()
            );

            $result = $regService->registerStudent([
                'matric_no'       => $data['matric_no'],
                'full_name'       => '',
                'rrr_number'      => $data['rrr_number'],
                'expected_amount' => (float) $session->fee_amount,
                'session_id'      => (int) $session->session_id,
            ]);

            // Handle optional photo upload — overwrites SIS photo for this student
            $photoPath = $result['data']['photo_path'] ?? 'photos/placeholder.jpg';

            if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
                $sanitized = preg_replace('/[^a-z0-9]/i', '_', strtolower($data['matric_no']));
                $filename  = 'upload_' . $sanitized . '_' . time() . '.jpg';
                $request->file('photo')->move(public_path('photos'), $filename);
                $photoPath = 'photos/' . $filename;

                DB::table('students')
                    ->where('matric_no', $data['matric_no'])
                    ->where('session_id', (int) $session->session_id)
                    ->update(['photo_path' => $photoPath]);

                DB::table('mock_sis')
                    ->where('matric_no', $data['matric_no'])
                    ->update(['photo_path' => $photoPath]);
            }

            // Build the QR SVG
            $tokenRow = DB::table('qr_tokens')
                ->where('token_id', $result['data']['token_id'])
                ->first();

            $qrService = new QrTokenService(new CryptoService());
            $qrSvg = $qrService->buildQrCode([
                'token_id'          => $result['data']['token_id'],
                'encrypted_payload' => $tokenRow->encrypted_payload,
                'hmac_signature'    => $tokenRow->hmac_signature,
                'session_id'        => (int) $session->session_id,
            ]);

            // Fetch department name
            $deptRow = DB::table('students')
                ->join('departments', 'students.department_id', '=', 'departments.dept_id')
                ->where('students.matric_no', $data['matric_no'])
                ->where('students.session_id', (int) $session->session_id)
                ->select('departments.dept_name')
                ->first();

            app(AuditService::class)->logAction(
                $data['matric_no'],
                'student',
                'student.registered',
                ['token_id' => $result['data']['token_id'], 'session_id' => $session->session_id]
            );

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data'    => array_merge($result['data'], [
                    'qr_svg'     => $qrSvg,
                    'department' => $deptRow->dept_name ?? '',
                    'session_id' => (int) $session->session_id,
                    'photo_path' => $photoPath,
                    'photo_url'  => '/' . $photoPath,
                ]),
            ]);

        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
