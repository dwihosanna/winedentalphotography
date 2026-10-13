<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PatientController;
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
    });
});
