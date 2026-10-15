<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/** Login dengan Google (FR-27). Pengguna baru diarahkan ke onboarding untuk syarat layanan dan nama praktik. */
class GoogleController extends Controller
{
    /** ?trust=1: pilihan "Percayai perangkat ini" di halaman login, dipakai lagi saat kembali dari Google. */
    public function redirect(Request $request): RedirectResponse
    {
        $request->session()->put('trust_device', $request->boolean('trust', true));

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors(['email' => 'Login Google gagal. Coba lagi.']);
        }

        $email = strtolower($google->getEmail());
        $user = User::where('google_id', $google->getId())->first() ?? User::where('email', $email)->first();

        if ($user === null) {
            $user = User::create([
                'name' => $google->getName() ?: $email,
                'email' => $email,
                'auth_provider' => 'google',
                'google_id' => $google->getId(),
            ]);
        } elseif ($user->google_id === null) {
            // Akun email yang sudah ada: tautkan ke Google, satu akun per email.
            $user->forceFill(['google_id' => $google->getId()])->save();
        }

        // Google sudah memverifikasi email ini.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        if ($user->isSuspended()) {
            return redirect()->route('login')->withErrors(['email' => 'Akun ditangguhkan. Hubungi admin.']);
        }

        Auth::login($user, remember: (bool) request()->session()->pull('trust_device', false));
        request()->session()->regenerate();

        return redirect()->intended(route('patients.index'));
    }
}
