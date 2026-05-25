<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\Web\AdminWebController;
use App\Http\Controllers\Web\ExaminerWebController;
use App\Http\Controllers\Web\StudentWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('home'));
Route::get('/health', [HealthController::class, 'check']);

// Student portal
Route::get('/student/register',  [StudentWebController::class, 'index']);
Route::post('/student/register', [StudentWebController::class, 'register']);

// Examiner portal (login-gated — NO registration)
Route::get('/examiner/login',      [ExaminerWebController::class, 'login']);
Route::post('/examiner/login',     [ExaminerWebController::class, 'doLogin']);
Route::get('/examiner/logout',     [ExaminerWebController::class, 'logout']);
Route::get('/examiner/dashboard',  [ExaminerWebController::class, 'index']);
Route::post('/examiner/verify',    [ExaminerWebController::class, 'verify']);

// Admin portal
Route::get('/admin/dashboard', [AdminWebController::class, 'index']);

// Passport photo thumbnails — resize + disk cache (GD)
Route::get('/photo-thumb/{name}', function (string $name) {
    $name = basename($name);
    if (! preg_match('/^[\w\-]+\.jpe?g$/i', $name)) {
        abort(404);
    }

    $srcPath = public_path('photos/' . $name);
    if (! file_exists($srcPath)) {
        abort(404);
    }

    $thumbDir  = storage_path('app/thumbs');
    $thumbPath = $thumbDir . '/' . $name;

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

    // Resize to 224×280 max (2× retina at 112×140 display size)
    $src = @imagecreatefromjpeg($srcPath);
    if (! $src) {
        abort(500);
    }
    [$ow, $oh] = [imagesx($src), imagesy($src)];
    $ratio = min(224 / $ow, 280 / $oh);
    $nw    = (int) round($ow * $ratio);
    $nh    = (int) round($oh * $ratio);

    $dst = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $ow, $oh);
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
})->where('name', '[^/]+');

// Documentation (replaces presentation)
Route::get('/documentation', fn () => view('documentation'));
Route::get('/presentation',  fn () => view('landing'));
