<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Student\AuthController as StudentAuthController;
use App\Http\Controllers\Student\ExamController as StudentExamController;
use App\Http\Controllers\Examiner\AuthController as ExaminerAuthController;
use App\Http\Controllers\Examiner\VerifyController as ExaminerVerifyController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use Illuminate\Support\Facades\Route;

// ── Student auth (public) ──────────────────────────────────────────────────────
Route::prefix('student')->group(function () {
    Route::post('register', [StudentAuthController::class, 'register']);
    Route::post('login', [StudentAuthController::class, 'login']);
});

// ── Examiner auth (public) ─────────────────────────────────────────────────────
Route::prefix('examiner')->group(function () {
    Route::post('login', [ExaminerAuthController::class, 'login']);
});

// ── Admin auth (public) ────────────────────────────────────────────────────────
Route::prefix('admin')->group(function () {
    Route::post('login', [AdminAuthController::class, 'login']);
});

// ── Shared protected routes (any authenticated role) ──────────────────────────
Route::middleware('auth:api')->prefix('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::get('me', [AuthController::class, 'me']);
});

// ── Student protected routes ───────────────────────────────────────────────────
Route::middleware('auth:api')->prefix('student')->group(function () {
    Route::post('register-exam', [StudentExamController::class, 'registerExam']);
});

// ── Examiner protected routes ──────────────────────────────────────────────────
Route::middleware(['auth:api', 'role:EXAMINER'])->prefix('examiner')->group(function () {
    Route::post('verify', [ExaminerVerifyController::class, 'verify']);
    Route::get('stats', [ExaminerVerifyController::class, 'stats']);
    Route::get('history', [ExaminerVerifyController::class, 'history']);
    Route::get('student-trace', [ExaminerVerifyController::class, 'studentTrace']);
});

// ── Admin protected routes ─────────────────────────────────────────────────────
Route::middleware(['auth:api', 'role:ADMIN,SUPER_ADMIN'])->prefix('admin')->group(function () {
    Route::get('sessions',                      [AdminDashboardController::class, 'sessions']);
    Route::post('sessions',                     [AdminDashboardController::class, 'createSession']);
    Route::patch('sessions/{id}/activate',      [AdminDashboardController::class, 'activateSession']);
    Route::get('examiners',                     [AdminDashboardController::class, 'examiners']);
    Route::post('examiners',                    [AdminDashboardController::class, 'createExaminer']);
    Route::patch('examiners/{id}/toggle',       [AdminDashboardController::class, 'toggleExaminer']);
    Route::post('tokens/{id}/revoke',           [AdminDashboardController::class, 'revokeToken']);
    Route::get('logs',                          [AdminDashboardController::class, 'logs']);
    Route::get('stats',                         [AdminDashboardController::class, 'stats']);
    Route::get('student-trace',                 [AdminDashboardController::class, 'studentTrace']);
    Route::get('audit-trail',                   [AdminDashboardController::class, 'auditTrail']);
});
