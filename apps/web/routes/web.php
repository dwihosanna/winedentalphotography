<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PhotoSessionController;
use App\Http\Middleware\EnsureOnboarded;
use Illuminate\Support\Facades\Route;

// Rute login/registrasi/verifikasi email didaftarkan oleh Fortify (config/fortify.php).

Route::redirect('/', '/pasien');

Route::middleware('guest')->group(function () {
    Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding');
    Route::post('/onboarding', [OnboardingController::class, 'store']);

    Route::middleware([EnsureOnboarded::class, 'workspace'])->group(function () {
        Route::resource('pasien', PatientController::class)
            ->parameters(['pasien' => 'patient'])
            ->names('patients')
            ->except('destroy'); // tidak ada hapus pasien: retensi rekam medis 25 tahun (SRS Bagian 9)

        Route::get('/pasien/{patient}/arsip', [PhotoSessionController::class, 'index'])->name('sessions.index');
        Route::get('/pasien/{patient}/sesi/baru', [PhotoSessionController::class, 'create'])->name('sessions.create');
        Route::post('/pasien/{patient}/sesi', [PhotoSessionController::class, 'store'])->name('sessions.store');
        Route::get('/sesi/{session}', [PhotoSessionController::class, 'show'])->name('sessions.show');
        Route::put('/sesi/{session}', [PhotoSessionController::class, 'update'])->name('sessions.update');

        Route::get('/sesi/{session}/kamera', [PhotoController::class, 'camera'])->name('sessions.camera');
        Route::get('/sesi/{session}/unggah', [PhotoController::class, 'upload'])->name('sessions.upload');
        Route::post('/sesi/{session}/foto', [PhotoController::class, 'store'])->name('photos.store');
        Route::get('/foto/{photo}/{variant}', [PhotoController::class, 'file'])
            ->middleware('signed')->whereIn('variant', ['thumb', 'original'])->name('photos.file');
    });
});
