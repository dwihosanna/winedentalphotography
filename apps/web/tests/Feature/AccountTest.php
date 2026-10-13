<?php

namespace Tests\Feature;

use App\Enums\MemberRole;
use App\Enums\Plan;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/** FR-27: registrasi email/Google, verifikasi email, syarat layanan, workspace Free. */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'drg. Ayu', 'practice_name' => 'Praktik Ayu', 'email' => 'Ayu@Example.com',
            'password' => 'rahasia-panjang-123', 'password_confirmation' => 'rahasia-panjang-123',
            'dentist_declaration' => '1', 'terms' => '1',
        ];
    }

    public function test_registration_creates_free_workspace_and_records_terms(): void
    {
        Notification::fake();

        $this->post('/register', $this->registration())->assertRedirect();

        $user = User::firstWhere('email', 'ayu@example.com');
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->hasAcceptedCurrentTerms());
        $membership = $user->memberships()->first();
        $this->assertSame(MemberRole::Dentist, $membership->role);
        $this->assertSame(Plan::Free, $membership->workspace->plan);
        $this->assertSame(50, $membership->workspace->patient_limit);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_requires_declaration_and_terms(): void
    {
        $this->post('/register', $this->registration(['dentist_declaration' => null, 'terms' => null]))
            ->assertSessionHasErrors(['dentist_declaration', 'terms']);

        $this->assertSame(0, User::count());
    }

    public function test_unverified_user_cannot_open_patients(): void
    {
        [$dentist] = $this->dentistWithWorkspace();
        $dentist->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($dentist)->get('/pasien')->assertRedirect('/email/verify');
    }

    public function test_new_terms_version_requires_acceptance_again(): void
    {
        [$dentist] = $this->dentistWithWorkspace();
        config(['dental.terms_version' => '2099-01-01']);

        $this->actingAs($dentist)->get('/pasien')->assertRedirect('/onboarding');
        $this->actingAs($dentist)->post('/onboarding', ['dentist_declaration' => '1', 'terms' => '1'])->assertRedirect('/pasien');
        $this->actingAs($dentist)->get('/pasien')->assertOk();
        $this->assertSame(1, $dentist->memberships()->count());
    }

    private function fakeGoogle(string $id, string $email, string $name = 'drg. Google'): void
    {
        $google = (new SocialiteUser)->map(['id' => $id, 'email' => $email, 'name' => $name]);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_google_login_creates_verified_user_and_asks_for_onboarding(): void
    {
        $this->fakeGoogle('g-123', 'Baru@Gmail.com');

        $this->get('/auth/google/callback')->assertRedirect('/pasien');
        $user = User::firstWhere('email', 'baru@gmail.com');
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame('google', $user->auth_provider);

        $this->get('/pasien')->assertRedirect('/onboarding');
        $this->post('/onboarding', ['practice_name' => 'Klinik Baru', 'dentist_declaration' => '1', 'terms' => '1'])->assertRedirect('/pasien');
        $this->get('/pasien')->assertOk()->assertSee('Belum ada pasien');
        $this->assertSame('Klinik Baru', $user->memberships()->first()->workspace->practice_name);
    }

    public function test_google_login_links_existing_email_account(): void
    {
        [$dentist] = $this->dentistWithWorkspace();
        $this->fakeGoogle('g-999', strtoupper($dentist->email));

        $this->get('/auth/google/callback')->assertRedirect();

        $this->assertAuthenticatedAs($dentist);
        $this->assertSame('g-999', $dentist->fresh()->google_id);
        $this->assertSame(1, User::count());
    }

    public function test_suspended_user_cannot_log_in_with_google(): void
    {
        [$dentist] = $this->dentistWithWorkspace();
        $dentist->forceFill(['status' => 'suspended'])->save();
        $this->fakeGoogle('g-1', $dentist->email);

        $this->get('/auth/google/callback')->assertRedirect('/login');
        $this->assertGuest();
    }
}
