<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Syarat layanan versi berlaku sudah disetujui dan pengguna punya workspace aktif. */
class EnsureOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->hasAcceptedCurrentTerms() || ! $user->memberships()->where('status', 'active')->exists()) {
            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
