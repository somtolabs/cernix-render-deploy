<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\Web\AdminWebController;
use App\Http\Controllers\Web\ExaminerWebController;
use App\Http\Controllers\Web\StudentDashboardController;
use App\Http\Controllers\Web\StudentWebController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));
Route::get('/health', [HealthController::class, 'check']);

// Student portal
Route::get('/student/register',  [StudentWebController::class, 'index'])->name('student.register');
Route::post('/student/lookup',   [StudentWebController::class, 'lookup'])->name('student.lookup');
Route::get('/student/onboard',   [StudentWebController::class, 'onboard'])->name('student.onboard');
Route::post('/student/onboard',  [StudentWebController::class, 'completeOnboarding'])->name('student.onboard.store');
Route::post('/student/register', [StudentWebController::class, 'register']);
Route::get('/student/login',     [StudentWebController::class, 'loginPage'])->name('student.login');
Route::post('/student/login',    [StudentWebController::class, 'doLogin'])->name('student.login.store');
Route::get('/student/dashboard', [StudentDashboardController::class, 'index'])->name('student.dashboard');
Route::get('/student/profile', [StudentDashboardController::class, 'profile'])->name('student.profile');
Route::post('/student/profile/photo', [StudentDashboardController::class, 'uploadPhoto'])->name('student.profile.photo.store');
Route::post('/student/profile/verification', [StudentDashboardController::class, 'resubmitVerification'])->name('student.profile.verification.store');
Route::post('/student/profile/photo-change-request', [StudentDashboardController::class, 'profilePhotoChangeRequestStore'])->name('student.profile.photo-change-request.store');
Route::get('/student/exam-access-id', [StudentDashboardController::class, 'examAccessId'])->name('student.exam-access-id');
Route::get('/student/exam-access-id/{timetable}', [StudentDashboardController::class, 'examAccessId'])
    ->whereNumber('timetable')
    ->name('student.exam-access-id.course');
Route::get('/student/timetable', [StudentDashboardController::class, 'timetable'])->name('student.timetable');
Route::get('/student/payment', [StudentDashboardController::class, 'payment'])->name('student.payment');
Route::get('/student/generate-exam-pass', [StudentDashboardController::class, 'generateExamPass'])->name('student.generate-exam-pass');
Route::post('/student/generate-exam-pass', [StudentDashboardController::class, 'storeExamPass'])->name('student.generate-exam-pass.store');
Route::post('/student/passes/{timetable}/generate', [StudentDashboardController::class, 'quickGeneratePass'])
    ->whereNumber('timetable')
    ->name('student.pass.quick-generate');
Route::get('/student/instructions', [StudentDashboardController::class, 'instructions'])->name('student.instructions');
Route::get('/student/notifications', [StudentDashboardController::class, 'notifications'])->name('student.notifications');
Route::post('/student/notifications/{note}/acknowledge', [StudentDashboardController::class, 'acknowledgeNotification'])->name('student.notifications.acknowledge');
Route::get('/student/scans/{log}', [StudentDashboardController::class, 'scanDetail'])->name('student.scans.show');
Route::get('/student/exam-pass', [StudentDashboardController::class, 'printPass'])->name('student.exam-pass');
Route::get('/student/exam-pass/print', [StudentDashboardController::class, 'printPass'])->name('student.pass.print');
Route::get('/student/exam-pass/{timetable}/print', [StudentDashboardController::class, 'printPass'])
    ->whereNumber('timetable')
    ->name('student.exam-pass.course');
Route::post('/student/logout', [StudentDashboardController::class, 'logout'])->name('student.logout');

// Examiner portal (login-gated — NO registration)
Route::get('/examiner/login',      [ExaminerWebController::class, 'login']);
Route::post('/examiner/login',     [ExaminerWebController::class, 'doLogin']);
Route::get('/examiner/logout',     [ExaminerWebController::class, 'logout']);
Route::get('/examiner/dashboard',  [ExaminerWebController::class, 'index'])->name('examiner.dashboard');
Route::post('/examiner/verify',    [ExaminerWebController::class, 'verify']);
Route::get('/examiner/metrics',    [ExaminerWebController::class, 'metrics'])->name('examiner.metrics');
Route::get('/examiner/history',    [ExaminerWebController::class, 'history'])->name('examiner.history');
Route::get('/examiner/scan-history', [ExaminerWebController::class, 'scanHistoryPage'])->name('examiner.scan-history');
Route::get('/examiner/student-records', [ExaminerWebController::class, 'studentRecordsPage'])->name('examiner.student-records');
Route::get('/examiner/audit',      [ExaminerWebController::class, 'audit'])->name('examiner.audit');
Route::get('/examiner/audit-trail', [ExaminerWebController::class, 'auditTrailPage'])->name('examiner.audit-trail');
Route::get('/examiner/today-exams', [ExaminerWebController::class, 'todayExamsPage'])->name('examiner.today-exams');
Route::get('/examiner/assessments/{timetableId}/export', [ExaminerWebController::class, 'exportAssessmentReport'])
    ->whereNumber('timetableId')
    ->name('examiner.assessments.export');
Route::get('/examiner/notifications', [ExaminerWebController::class, 'notificationsPage'])->name('examiner.notifications');
Route::post('/examiner/notifications/{note}/acknowledge', [ExaminerWebController::class, 'acknowledgeNotification'])->name('examiner.notifications.acknowledge');
Route::get('/examiner/scans/{log}', [ExaminerWebController::class, 'showScan'])->name('examiner.scans.show');
Route::post('/examiner/submit-attendance', [ExaminerWebController::class, 'submitAttendance'])->name('examiner.submit-attendance');
Route::post('/examiner/scan-session/start', [ExaminerWebController::class, 'startScanSession'])->name('examiner.scan-session.start');
Route::post('/examiner/scan-session/stop',  [ExaminerWebController::class, 'stopScanSession'])->name('examiner.scan-session.stop');

// Admin portal
Route::get('/admin/login', [ExaminerWebController::class, 'adminLogin'])->name('admin.login');
Route::post('/admin/login', [ExaminerWebController::class, 'adminDoLogin']);
Route::get('/admin/logout', [ExaminerWebController::class, 'adminLogout'])->name('admin.logout');
Route::get('/admin/notes', [AdminWebController::class, 'notes'])->name('admin.notes');
Route::post('/admin/notes', [AdminWebController::class, 'noteStore'])->name('admin.notes.store');
Route::patch('/admin/notes/{note}/resolve', [AdminWebController::class, 'noteResolve'])->name('admin.notes.resolve');
Route::get('/admin/dashboard', [AdminWebController::class, 'index'])->name('admin.dashboard');
Route::get('/admin/intelligence', [AdminWebController::class, 'intelligence'])->name('admin.intelligence');
Route::get('/admin/student-registry', [AdminWebController::class, 'studentRegistry'])->name('admin.student-registry');
Route::post('/admin/student-registry/import', [AdminWebController::class, 'studentRegistryImport'])->name('admin.student-registry.import');
Route::get('/admin/student-registry/{import}/rejected-rows', [AdminWebController::class, 'studentRegistryRejectedRows'])->name('admin.student-registry.rejected-rows');
Route::get('/admin/photo-approvals', [AdminWebController::class, 'photoApprovals'])->name('admin.photo-approvals');
Route::post('/admin/photo-approvals/approve', [AdminWebController::class, 'photoApprove'])->name('admin.photo-approvals.approve');
Route::post('/admin/photo-approvals/reject', [AdminWebController::class, 'photoReject'])->name('admin.photo-approvals.reject');
Route::post('/admin/photo-approvals/flag', [AdminWebController::class, 'photoFlag'])->name('admin.photo-approvals.flag');
Route::get('/admin/profile-photo-change-requests', [AdminWebController::class, 'profilePhotoChangeRequests'])->name('admin.profile-photo-change-requests');
Route::post('/admin/profile-photo-change-requests/approve', [AdminWebController::class, 'profilePhotoChangeRequestApprove'])->name('admin.profile-photo-change-requests.approve');
Route::post('/admin/profile-photo-change-requests/reject', [AdminWebController::class, 'profilePhotoChangeRequestReject'])->name('admin.profile-photo-change-requests.reject');
Route::get('/admin/students', [AdminWebController::class, 'students'])->name('admin.students');
Route::get('/admin/student-trace', [AdminWebController::class, 'studentTrace'])->name('admin.student-trace');
Route::get('/admin/students/{student}', [AdminWebController::class, 'studentShow'])->where('student', '.*')->name('admin.students.show');
Route::patch('/admin/students/{student}/account-status', [AdminWebController::class, 'studentAccountStatus'])->where('student', '.*')->name('admin.students.account-status');
Route::get('/admin/examiners', [AdminWebController::class, 'examiners'])->name('admin.examiners');
Route::post('/admin/examiners', [AdminWebController::class, 'examinerStore'])->name('admin.examiners.store');
Route::patch('/admin/examiners/{examiner}/toggle', [AdminWebController::class, 'examinerToggle'])->name('admin.examiners.toggle');
Route::get('/admin/examiners/{examiner}', [AdminWebController::class, 'examinerShow'])->name('admin.examiners.show');
Route::get('/admin/payments', [AdminWebController::class, 'payments'])->name('admin.payments');
Route::get('/admin/payments/student/{student}', [AdminWebController::class, 'paymentShowByStudent'])->where('student', '.*')->name('admin.payments.student.show');
Route::get('/admin/payments/{rrr}', [AdminWebController::class, 'paymentShow'])->where('rrr', '.*')->name('admin.payments.show');
Route::get('/admin/timetable', [AdminWebController::class, 'timetable'])->name('admin.timetable');
Route::post('/admin/timetable', [AdminWebController::class, 'timetableStore'])->name('admin.timetable.store');
Route::put('/admin/timetable/{entry}', [AdminWebController::class, 'timetableUpdate'])->name('admin.timetable.update');
Route::delete('/admin/timetable/{entry}', [AdminWebController::class, 'timetableDestroy'])->name('admin.timetable.destroy');
Route::post('/admin/timetable/import', [AdminWebController::class, 'timetableImport'])->name('admin.timetable.import');
Route::post('/admin/timetable/{entry}/roster', [AdminWebController::class, 'timetableRosterAdd'])->name('admin.timetable.roster.add');
Route::delete('/admin/timetable/{entry}/roster/{matric}', [AdminWebController::class, 'timetableRosterRemove'])->where('matric', '.*')->name('admin.timetable.roster.remove');
Route::post('/admin/timetable/{entry}/roster/import', [AdminWebController::class, 'timetableRosterImport'])->name('admin.timetable.roster.import');
Route::get('/admin/scan-logs', [AdminWebController::class, 'scanLogs'])->name('admin.scan-logs');
Route::get('/admin/scan-logs/{log}', [AdminWebController::class, 'scanLogShow'])->name('admin.scan-logs.show');
Route::get('/admin/qr-tokens', [AdminWebController::class, 'qrTokens'])->name('admin.qr-tokens');
Route::patch('/admin/qr-tokens/{token}/revoke', [AdminWebController::class, 'qrTokenRevoke'])->name('admin.qr-tokens.revoke');
Route::get('/admin/exam-sessions', [AdminWebController::class, 'examSessions'])->name('admin.exam-sessions');
Route::get('/admin/attendance',   [AdminWebController::class, 'attendance'])->name('admin.attendance');
Route::get('/admin/activity', [AdminWebController::class, 'activity'])->name('admin.activity');
Route::get('/admin/settings', [AdminWebController::class, 'settings'])->name('admin.settings');
Route::get('/admin/diagnostics/persistence', [AdminWebController::class, 'persistenceDiagnostics'])->name('admin.diagnostics.persistence');
// TEMPORARY: object-storage probe for Render free tier (no shell). Remove once R2 fix is confirmed.
Route::get('/admin/media-diagnose', [AdminWebController::class, 'mediaDiagnostics'])->name('admin.media-diagnose');
Route::patch('/admin/settings/fees', [AdminWebController::class, 'settingsFeesUpdate'])->name('admin.settings.fees.update');
Route::patch('/admin/settings/live-phase', [AdminWebController::class, 'settingsLivePhaseUpdate'])->name('admin.settings.live-phase.update');
Route::patch('/admin/settings/demo-mode', [AdminWebController::class, 'settingsDemoUpdate'])->name('admin.settings.demo.update');
Route::post('/admin/settings/branding', [AdminWebController::class, 'settingsBrandingUpdate'])->name('admin.settings.branding.update');
Route::patch('/admin/sessions/{session}/activate', [AdminWebController::class, 'sessionActivate'])->name('admin.sessions.activate');
Route::patch('/admin/sessions/{session}/close', [AdminWebController::class, 'sessionClose'])->name('admin.sessions.close');
Route::patch('/admin/sessions/{session}/update', [AdminWebController::class, 'sessionUpdate'])->name('admin.sessions.update');
Route::post('/admin/settings/clear-demo', [AdminWebController::class, 'clearDemoData'])->name('admin.settings.clear-demo');
Route::post('/admin/settings/clear-live', [AdminWebController::class, 'clearLiveData'])->name('admin.settings.clear-live');
Route::post('/admin/settings/clear-assessments', [AdminWebController::class, 'clearAssessments'])->name('admin.settings.clear-assessments');
Route::post('/admin/settings/clear-attendance', [AdminWebController::class, 'clearAttendanceRecords'])->name('admin.settings.clear-attendance');
Route::post('/admin/settings/clear-qr-tokens', [AdminWebController::class, 'clearQrTokens'])->name('admin.settings.clear-qr-tokens');
Route::post('/admin/settings/clear-payments', [AdminWebController::class, 'clearPaymentRecords'])->name('admin.settings.clear-payments');
Route::post('/admin/settings/clear-verification-logs', [AdminWebController::class, 'clearVerificationLogs'])->name('admin.settings.clear-verification-logs');
Route::post('/admin/settings/clear-audit-logs', [AdminWebController::class, 'clearAuditLogs'])->name('admin.settings.clear-audit-logs');
Route::post('/admin/settings/reset-branding', [AdminWebController::class, 'resetBranding'])->name('admin.settings.reset-branding');
Route::post('/admin/settings/clear-students', [AdminWebController::class, 'clearStudentRecords'])->name('admin.settings.clear-students');
Route::post('/admin/settings/clear-examiners', [AdminWebController::class, 'clearExaminerAccounts'])->name('admin.settings.clear-examiners');
Route::get('/admin/id-card/{matric}', [AdminWebController::class, 'serveIdCard'])->where('matric', '.*')->name('admin.id-card');
Route::get('/admin/verification-selfie/{matric}', [AdminWebController::class, 'serveVerificationSelfie'])->where('matric', '.*')->name('admin.verification-selfie');
Route::get('/admin/session-audits', [AdminWebController::class, 'sessionAudits'])->name('admin.session-audits');
Route::get('/admin/session-audits/{id}', [AdminWebController::class, 'sessionAuditShow'])->name('admin.session-audits.show');
Route::get('/admin/live-sessions', [AdminWebController::class, 'liveSessions'])->name('admin.live-sessions');

// Passport photo thumbnails — resize + in-memory cache (GD).
//
// Media lives in object storage, so the source is fetched by storage key via
// MediaService. Nothing is read from or written to local disk: the Render
// container filesystem is ephemeral and would lose the cache on restart.
Route::get('/photo-thumb/{path}', function (string $path) {
    $path = ltrim(str_replace('\\', '/', $path), '/');

    if (str_contains($path, '..') || preg_match('/^https?:/i', $path) || ! preg_match('/^[\w\-\/]+\.(jpe?g|png|webp|gif|heic|heif)$/i', $path)) {
        abort(404);
    }

    $media = app(App\Services\MediaService::class)->findByStorageKey($path);

    if (! $media) {
        abort(404);
    }

    // A verification selfie or ID card must never be reachable here: this route
    // is unauthenticated and its URL is embedded in pages. Those are admin-only
    // and are served by short-lived signed URLs instead.
    if ($media->purpose !== App\Models\Media::PURPOSE_PROFILE_PHOTO) {
        abort(404);
    }

    $cacheKey = 'photo-thumb:' . md5('passport-v3-' . $path);

    if ($cached = Cache::get($cacheKey)) {
        return response($cached, 200, [
            'Content-Type'  => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    $raw = app(App\Services\MediaService::class)->contents($media);
    $src = $raw ? @imagecreatefromstring($raw) : false;
    if (! $src) {
        abort(404);
    }
    [$ow, $oh] = [imagesx($src), imagesy($src)];
    [$tw, $th] = [240, 320];
    $targetRatio = $tw / $th;
    $sourceRatio = $ow / max($oh, 1);

    if ($sourceRatio > $targetRatio) {
        $cropH = $oh;
        $cropW = (int) round($oh * $targetRatio);
        $cropX = (int) max(0, floor(($ow - $cropW) / 2));
        $cropY = 0;
    } else {
        $cropW = $ow;
        $cropH = (int) round($ow / $targetRatio);
        $cropX = 0;
        $cropY = (int) max(0, floor(($oh - $cropH) * .28));
    }

    $dst = imagecreatetruecolor($tw, $th);
    imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $tw, $th, $cropW, $cropH);
    imagedestroy($src);

    ob_start();
    imagejpeg($dst, null, 82);
    imagedestroy($dst);
    $jpeg = ob_get_clean();

    Cache::put($cacheKey, $jpeg, now()->addDay());

    return response($jpeg, 200, [
        'Content-Type'  => 'image/jpeg',
        'Cache-Control' => 'private, max-age=86400',
    ]);
})->where('path', '.*');

// Documentation (replaces presentation)
Route::get('/documentation', fn () => view('documentation'));
Route::get('/presentation',  fn () => view('landing'));
