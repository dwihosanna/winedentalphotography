<?php

namespace App\Actions;

use App\Enums\MemberRole;
use App\Enums\Plan;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Langkah terakhir pendaftaran dokter, sama untuk email dan Google: catat persetujuan syarat layanan +
 * pernyataan dokter berizin, lalu buat workspace Free dengan dokter sebagai pemilik (SRS 5.3).
 */
class CreateDentistWorkspace
{
    public function __invoke(User $user, string $practiceName, Request $request): Workspace
    {
        return DB::transaction(function () use ($user, $practiceName, $request) {
            TermsAcceptance::create([
                'user_id' => $user->id,
                'terms_version' => config('dental.terms_version'),
                'dentist_declaration' => true,
                'accepted_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $workspace = Workspace::create([
                'practice_name' => $practiceName,
                'owner_user_id' => $user->id,
                'plan' => Plan::Free,
                'patient_limit' => Plan::Free->patientLimit(),
                'storage_limit_bytes' => Plan::Free->storageLimitBytes(),
            ]);
            $workspace->members()->create(['user_id' => $user->id, 'role' => MemberRole::Dentist]);

            return $workspace;
        });
    }
}
