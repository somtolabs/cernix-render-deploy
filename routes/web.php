<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\Web\AdminWebController;
use App\Http\Controllers\Web\ExaminerWebController;
use App\Http\Controllers\Web\StudentDashboardController;
use App\Http\Controllers\Web\StudentWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));
Route::get('/health', [HealthController::class, 'check']);

// Student portal
Route::get('/student/register',  [StudentWebController::class, 'index'])->name('student.register');
Route::post('/student/register', [StudentWebController::class, 'register']);
Route::get('/student/login', fn () => redirect()->route('student.register'))->name('student.login');
Route::get('/student/dashboard', [StudentDashboardController::class, 'index'])->name('student.dashboard');
Route::get('/student/profile', [StudentDashboardController::class, 'profile'])->name('student.profile');
Route::get('/student/exam-access-id', [StudentDashboardController::class, 'examAccessId'])->name('student.exam-access-id');
Route::get('/student/timetable', [StudentDashboardController::class, 'timetable'])->name('student.timetable');
Route::get('/student/payment', [StudentDashboardController::class, 'payment'])->name('student.payment');
Route::get('/student/instructions', [StudentDashboardController::class, 'instructions'])->name('student.instructions');
Route::get('/student/notifications', [StudentDashboardController::class, 'notifications'])->name('student.notifications');
Route::post('/student/notifications/{note}/acknowledge', [StudentDashboardController::class, 'acknowledgeNotification'])->name('student.notifications.acknowledge');
Route::get('/student/scans/{log}', [StudentDashboardController::class, 'scanDetail'])->name('student.scans.show');
Route::get('/student/exam-pass', [StudentDashboardController::class, 'printPass'])->name('student.exam-pass');
Route::get('/student/exam-pass/print', [StudentDashboardController::class, 'printPass'])->name('student.pass.print');
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
Route::get('/examiner/notifications', [ExaminerWebController::class, 'notificationsPage'])->name('examiner.notifications');
Route::post('/examiner/notifications/{note}/acknowledge', [ExaminerWebController::class, 'acknowledgeNotification'])->name('examiner.notifications.acknowledge');
Route::get('/examiner/scans/{log}', [ExaminerWebController::class, 'showScan'])->name('examiner.scans.show');

// Admin portal
Route::get('/admin/login', [ExaminerWebController::class, 'adminLogin'])->name('admin.login');
Route::post('/admin/login', [ExaminerWebController::class, 'adminDoLogin']);
Route::get('/admin/logout', [ExaminerWebController::class, 'adminLogout'])->name('admin.logout');
Route::get('/admin/notes', [AdminWebController::class, 'notes'])->name('admin.notes');
Route::post('/admin/notes', [AdminWebController::class, 'noteStore'])->name('admin.notes.store');
Route::patch('/admin/notes/{note}/resolve', [AdminWebController::class, 'noteResolve'])->name('admin.notes.resolve');
Route::get('/admin/dashboard', [AdminWebController::class, 'index'])->name('admin.dashboard');
Route::get('/admin/intelligence', [AdminWebController::class, 'intelligence'])->name('admin.intelligence');
Route::get('/admin/students', [AdminWebController::class, 'students'])->name('admin.students');
Route::get('/admin/student-trace', [AdminWebController::class, 'studentTrace'])->name('admin.student-trace');
Route::get('/admin/students/{student}', [AdminWebController::class, 'studentShow'])->where('student', '.*')->name('admin.students.show');
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
Route::get('/admin/scan-logs', [AdminWebController::class, 'scanLogs'])->name('admin.scan-logs');
Route::get('/admin/scan-logs/{log}', [AdminWebController::class, 'scanLogShow'])->name('admin.scan-logs.show');
Route::get('/admin/activity', [AdminWebController::class, 'activity'])->name('admin.activity');
Route::get('/admin/settings', [AdminWebController::class, 'settings'])->name('admin.settings');
Route::patch('/admin/settings/fees', [AdminWebController::class, 'settingsFeesUpdate'])->name('admin.settings.fees.update');
Route::patch('/admin/settings/demo-mode', [AdminWebController::class, 'settingsDemoUpdate'])->name('admin.settings.demo.update');
Route::patch('/admin/sessions/{session}/activate', [AdminWebController::class, 'sessionActivate'])->name('admin.sessions.activate');
Route::patch('/admin/sessions/{session}/close', [AdminWebController::class, 'sessionClose'])->name('admin.sessions.close');

// Passport photo thumbnails — resize + disk cache (GD)
Route::get('/photo-thumb/{path}', function (string $path) {
    $path = ltrim(str_replace('\\', '/', $path), '/');

    if (str_contains($path, '..') || preg_match('/^https?:/i', $path) || ! preg_match('/^[\w\-\/]+\.jpe?g$/i', $path)) {
        abort(404);
    }

    if (! str_contains($path, '/')) {
        $path = 'photos/' . $path;
    }

    if (! str_starts_with($path, 'photos/') && ! str_starts_with($path, 'demo-passports/')) {
        abort(404);
    }

    $srcPath = public_path($path);
    if (! file_exists($srcPath)) {
        abort(404);
    }

    $thumbDir  = storage_path('app/thumbs');
    $thumbPath = $thumbDir . '/' . md5('passport-v2-' . $path) . '-' . basename($path);

    if (! is_dir($thumbDir)) {
        mkdir($thumbDir, 0755, true);
    }

    // Serve disk-cached thumb if source is unchanged
    if (file_exists($thumbPath) && filemtime($thumbPath) >= filemtime($srcPath)) {
        return response()->file($thumbPath, [
            'Content-Type'  => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    // Crop to a consistent passport-style 3:4 frame.
    $src = @imagecreatefromjpeg($srcPath);
    if (! $src) {
        abort(500);
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

    file_put_contents($thumbPath, $jpeg);

    return response($jpeg, 200, [
        'Content-Type'  => 'image/jpeg',
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*');

// Documentation (replaces presentation)
Route::get('/documentation', fn () => view('documentation'));
Route::get('/presentation',  fn () => view('landing'));
