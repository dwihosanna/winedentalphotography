<?php

namespace App\Http\Middleware;

use App\Support\TrustedDevices;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Masuk otomatis dari perangkat tepercaya saat sesi sudah habis (atau sesi dibuka di PWA lagi), tanpa login ulang.
 * Berjalan setelah sesi dimulai dan sebelum middleware auth (lihat bootstrap/app.php).
 */
class LoginFromTrustedDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guest() && ($device = TrustedDevices::current($request)) && ! $device->user->isSuspended()) {
            Auth::login($device->user);
            $request->session()->regenerate();
            TrustedDevices::touch($device, $request);
        }

        return $next($request);
    }
}
