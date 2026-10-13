<?php

namespace Tests;

use App\Enums\MemberRole;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Jadikan $workspace konteks aktif untuk kode tes (di luar request HTTP). */
    protected function inWorkspace(Workspace $workspace, MemberRole $role = MemberRole::Dentist): Workspace
    {
        app(CurrentWorkspace::class)->set($workspace, $role);

        return $workspace;
    }

    /** Dokter pemilik workspace beserta keanggotaannya. */
    protected function dentistWithWorkspace(bool $premium = false): array
    {
        $dentist = User::factory()->create();
        $workspace = Workspace::factory()->when($premium, fn ($f) => $f->premium())->create(['owner_user_id' => $dentist->id]);
        $workspace->members()->create(['user_id' => $dentist->id, 'role' => MemberRole::Dentist]);

        TermsAcceptance::create([
            'user_id' => $dentist->id, 'terms_version' => config('dental.terms_version'),
            'dentist_declaration' => true, 'accepted_at' => now(),
        ]);

        return [$dentist, $workspace];
    }

    /** Asisten aktif di $workspace (sudah menyetujui syarat layanan). */
    protected function assistantIn(Workspace $workspace): User
    {
        $assistant = User::factory()->create();
        $workspace->members()->create(['user_id' => $assistant->id, 'role' => MemberRole::Assistant]);
        TermsAcceptance::create([
            'user_id' => $assistant->id, 'terms_version' => config('dental.terms_version'),
            'dentist_declaration' => false, 'accepted_at' => now(),
        ]);

        return $assistant;
    }
}
