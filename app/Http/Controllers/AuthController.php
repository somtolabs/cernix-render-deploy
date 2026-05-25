<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return response()->json([
            'status'  => 'success',
            'message' => 'Successfully logged out',
        ]);
    }

    public function refresh(): JsonResponse
    {
        $token = $this->authService->refresh();

        return response()->json([
            'status'  => 'success',
            'message' => 'Token refreshed',
            'data'    => $this->authService->tokenPayload($token),
        ]);
    }

    public function me(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => [
                'user' => $this->authService->me(),
            ],
        ]);
    }
}
