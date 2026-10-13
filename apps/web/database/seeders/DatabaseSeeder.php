<?php

namespace Database\Seeders;

use App\Enums\MemberRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SlotTemplateSeeder::class);

        // Akun dokter contoh hanya untuk lokal; produksi memakai registrasi (FR-27).
        if (app()->isLocal()) {
            $dentist = User::factory()->create(['name' => 'Dokter Contoh', 'email' => 'dokter@example.com']);
            $workspace = Workspace::factory()->premium()->create(['owner_user_id' => $dentist->id, 'practice_name' => 'Praktik Contoh']);
            $workspace->members()->create(['user_id' => $dentist->id, 'role' => MemberRole::Dentist]);
            $dentist->termsAcceptances()->create([
                'terms_version' => config('dental.terms_version'), 'dentist_declaration' => true, 'accepted_at' => now(),
            ]);
        }
    }
}
