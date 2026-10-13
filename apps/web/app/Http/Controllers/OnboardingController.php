<?php

namespace App\Http\Controllers;

use App\Actions\CreateDentistWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Untuk akun Google baru (atau syarat layanan versi baru): pernyataan dokter, syarat layanan, nama praktik. */
class OnboardingController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->hasAcceptedCurrentTerms() && $user->memberships()->where('status', 'active')->exists()) {
            return redirect()->route('patients.index');
        }

        return view('onboarding', ['needsWorkspace' => ! $user->memberships()->exists()]);
    }

    public function store(Request $request, CreateDentistWorkspace $createWorkspace): RedirectResponse
    {
        $user = $request->user();
        $needsWorkspace = ! $user->memberships()->exists();

        $data = $request->validate([
            'practice_name' => [$needsWorkspace ? 'required' : 'nullable', 'string', 'max:255'],
            'dentist_declaration' => ['accepted'],
            'terms' => ['accepted'],
        ], [
            'dentist_declaration.accepted' => 'Centang pernyataan bahwa Anda dokter gigi berizin.',
            'terms.accepted' => 'Setujui syarat layanan untuk melanjutkan.',
        ]);

        if ($needsWorkspace) {
            $createWorkspace($user, $data['practice_name'], $request);
        } else {
            $user->termsAcceptances()->create([
                'terms_version' => config('dental.terms_version'),
                'dentist_declaration' => true,
                'accepted_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return redirect()->route('patients.index');
    }
}
