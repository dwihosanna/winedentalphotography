<?php

namespace App\Support;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * "Percayai perangkat ini": token acak per perangkat di cookie (terenkripsi, HttpOnly), hash-nya di database.
 * Berlaku bergulir (diperpanjang saat dipakai). Keluar hanya mencabut perangkat itu sendiri.
 */
final class TrustedDevices
{
    public const COOKIE = 'dp_device';

    public static function issue(User $user, Request $request): void
    {
        self::forget($request); // ganti token lama perangkat ini bila ada
        $token = Str::random(64);
        TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'name' => self::deviceName($request->userAgent()),
            'last_used_at' => now(),
            'expires_at' => now()->addDays(self::days()),
        ]);
        self::queueCookie($token);
    }

    /** Perangkat tepercaya yang masih berlaku untuk request ini, atau null. */
    public static function current(Request $request): ?TrustedDevice
    {
        $token = $request->cookie(self::COOKIE);
        if (! is_string($token) || strlen($token) !== 64) {
            return null;
        }

        return TrustedDevice::with('user')->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())->first();
    }

    /** Perpanjang masa berlaku (paling sering sekali sehari agar tidak menulis database di setiap request). */
    public static function touch(TrustedDevice $device, Request $request): void
    {
        if ($device->last_used_at->gt(now()->subDay())) {
            return;
        }
        $device->forceFill(['last_used_at' => now(), 'expires_at' => now()->addDays(self::days())])->save();
        self::queueCookie($request->cookie(self::COOKIE));
    }

    public static function forget(Request $request): void
    {
        $token = $request->cookie(self::COOKIE);
        if (is_string($token) && $token !== '') {
            TrustedDevice::where('token_hash', hash('sha256', $token))->delete();
        }
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    public static function isCurrent(TrustedDevice $device, Request $request): bool
    {
        $token = $request->cookie(self::COOKIE);

        return is_string($token) && hash_equals($device->token_hash, hash('sha256', $token));
    }

    private static function days(): int
    {
        return (int) config('dental.trusted_device_days');
    }

    private static function queueCookie(string $token): void
    {
        // secure mengikuti SESSION_SECURE_COOKIE; httpOnly agar tidak terbaca JavaScript; lax agar ikut saat kembali dari Google.
        Cookie::queue(Cookie::make(self::COOKIE, $token, self::days() * 24 * 60, '/', null, config('session.secure'), true, false, 'lax'));
    }

    /** Nama perangkat singkat dari user agent, mis. "iPhone · Safari" atau "Mac · Chrome". */
    public static function deviceName(?string $agent): string
    {
        $agent ??= '';
        $device = match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Macintosh') => 'Mac/iPad',
            str_contains($agent, 'Windows') => 'Windows',
            default => 'Perangkat',
        };
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'CriOS') || str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'FxiOS') || str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        return "{$device} · {$browser}";
    }
}
