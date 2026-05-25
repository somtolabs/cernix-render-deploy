<?php

namespace App\Services;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    /**
     * Attempt login for a specific role. Returns the JWT token or false.
     */
    public function attemptLogin(array $credentials, string $role): string|false
    {
        $token = Auth::guard('api')->attempt($credentials);

        if (! $token) {
            return false;
        }

        /** @var User $user */
        $user = Auth::guard('api')->user();

        $actual = Roles::normalize($user->role);
        $expected = Roles::normalize($role);

        if ($expected === Roles::ADMIN && ! Roles::isAdminLike($actual)) {
            Auth::guard('api')->logout();
            return false;
        }

        if ($expected !== Roles::ADMIN && $actual !== $expected) {
            Auth::guard('api')->logout();
            return false;
        }

        return $token;
    }

    public function logout(): void
    {
        Auth::guard('api')->logout();
    }

    public function refresh(): string
    {
        return Auth::guard('api')->refresh();
    }

    public function me(): User
    {
        return Auth::guard('api')->user();
    }

    /**
     * Build the standard token payload for responses.
     */
    public function tokenPayload(string $token): array
    {
        return [
            'token'      => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
        ];
    }
}
