<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\CryptoService;
use App\Services\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ExaminerWebController extends Controller
{
    public function login(Request $request)
    {
        if ($request->session()->has('examiner_id')) {
            return redirect('/examiner/dashboard');
        }

        return view('examiner.login');
    }

    public function doLogin(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => 'required|string|max:100',
            'password' => 'required|string|max:200',
        ]);

        $examiner = DB::table('examiners')
            ->where('username', $credentials['username'])->where('is_active', true)
            ->first();

        if (! $examiner || ! Hash::check($credentials['password'], $examiner->password_hash)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $request->session()->regenerate();
        $request->session()->put('examiner_id', (int) $examiner->examiner_id);
        $request->session()->put('examiner_username', $examiner->username);
        $request->session()->put('examiner_name', $examiner->full_name);
        $request->session()->put('examiner_role', $examiner->role);

        app(AuditService::class)->logAction(
            (string) $examiner->examiner_id,
            'examiner',
            'examiner.login',
            ['username' => $examiner->username]
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Login successful',
            'data'    => [
                'examiner_id' => $examiner->examiner_id,
                'full_name'   => $examiner->full_name,
                'username'    => $examiner->username,
                'role'        => $examiner->role,
            ],
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $examinerId = $request->session()->get('examiner_id');

        if ($examinerId) {
            app(AuditService::class)->logAction(
                (string) $examinerId,
                'examiner',
                'examiner.logout',
                []
            );
        }

        $request->session()->flush();
        $request->session()->regenerate();

        return redirect('/examiner/login');
    }

    public function index(Request $request)
    {
        if (! $request->session()->has('examiner_id')) {
            return redirect('/examiner/login');
        }

        $examiner = [
            'id'        => $request->session()->get('examiner_id'),
            'full_name' => $request->session()->get('examiner_name'),
            'username'  => $request->session()->get('examiner_username'),
            'role'      => $request->session()->get('examiner_role'),
        ];

        return view('examiner.dashboard', compact('examiner'));
    }

    public function verify(Request $request): JsonResponse
    {
        if (! $request->session()->has('examiner_id')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Not authenticated.',
            ], 401);
        }

        $data = $request->validate([
            'qr_data' => 'required|array',
        ]);

        $examinerId = (int) $request->session()->get('examiner_id');

        $deviceFp = substr(md5($request->userAgent() ?? 'unknown'), 0, 16);
        $ip       = $request->ip() ?? '0.0.0.0';

        try {
            $service = new VerificationService(new CryptoService());
            $result  = $service->verifyQr($data['qr_data'], $examinerId, $deviceFp, $ip);

            // Surface examiner identity for the verification card
            $result['examiner'] = $request->session()->get('examiner_name', 'Examiner');

            if ($result['status'] === 'APPROVED') {
                app(AuditService::class)->logAction(
                    (string) $examinerId,
                    'examiner',
                    'scan.approved',
                    ['token_id' => $result['token_id']]
                );
            }

            return response()->json($result);

        } catch (\Throwable $e) {
            return response()->json([
                'status'    => 'REJECTED',
                'student'   => null,
                'token_id'  => null,
                'timestamp' => now()->toIso8601String(),
            ]);
        }
    }
}
