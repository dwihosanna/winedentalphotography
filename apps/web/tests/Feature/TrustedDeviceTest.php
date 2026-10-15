<?php

namespace Tests\Feature;

use App\Models\TrustedDevice;
use App\Models\User;
use App\Support\TrustedDevices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/** "Percayai perangkat ini": login sekali per perangkat; keluar di satu perangkat tidak mengeluarkan perangkat lain. */
class TrustedDeviceTest extends TestCase
{
    use RefreshDatabase;

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.1 Mobile/15E148 Safari/604.1';

    private const MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36';

    /** Login di "perangkat" dengan user agent tertentu; kembalikan token perangkat dari cookie (atau null). */
    private function loginOn(User $user, string $agent, bool $trust = true): ?string
    {
        $res = $this->withHeader('User-Agent', $agent)
            ->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => $trust ? '1' : null])
            ->assertRedirect();
        $token = $res->getCookie(TrustedDevices::COOKIE)?->getValue();
        $this->endSession();

        return $token;
    }

    /** Sesi habis / perangkat lain: tanpa sesi dan tanpa user di guard, hanya cookie perangkat (bila ada). */
    private function endSession(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
    }

    public function test_trusted_device_stays_logged_in_after_session_ends(): void
    {
        [$dentist] = $this->dentistWithWorkspace();
        $token = $this->loginOn($dentist, self::IPHONE);

        $this->assertNotNull($token);
        $device = TrustedDevice::sole();
        $this->assertSame('iPhone · Safari', $device->name);
        $this->assertSame(hash('sha256', $token), $device->token_hash);

        $this->withCookie(TrustedDevices::COOKIE, $token)->get('/pasien')->assertOk();
        $this->assertAuthenticatedAs($dentist);
    }

    public function test_without_trust_no_device_is_remembered(): void
    {
        [$dentist] = $this->dentistWithWorkspace();

        $this->assertNull($this->loginOn($dentist, self::IPHONE, trust: false));
        $this->assertSame(0, TrustedDevice::count());
        $this->get('/pasien')->assertRedirect('/login');
    }

    public function test_logout_on_one_device_keeps_the_other_logged_in(): void
    {
        [$dentist] = $this->dentistWithWorkspace();
        $phone = $this->loginOn($dentist, self::IPHONE);
        $laptop = $this->loginOn($dentist, self::MAC);
        $this->assertSame(2, TrustedDevice::count());

        // Laptop: keluar → hanya laptop yang dicabut.
        $this->withCookie(TrustedDevices::COOKIE, $laptop)->get('/pasien')->assertOk();
        $this->withCookie(TrustedDevices::COOKIE, $laptop)->post('/logout')->assertRedirect();
        $this->endSession();
        $this->assertSame(['iPhone · Safari'], TrustedDevice::pluck('name')->all());

        $this->withCookie(TrustedDevices::COOKIE, $laptop)->get('/pasien')->assertRedirect('/login');
        $this->endSession();
        $this->withCookie(TrustedDevices::COOKIE, $phone)->get('/pasien')->assertOk();
    }

    public function test_expired_device_or_suspended_user_must_log_in_again(): void
    {
        [$dentist] = $this->dentistWithWorkspace();
        $token = $this->loginOn($dentist, self::IPHONE);

        TrustedDevice::query()->update(['expires_at' => now()->subMinute()]);
        $this->withCookie(TrustedDevices::COOKIE, $token)->get('/pasien')->assertRedirect('/login');

        $this->endSession();
        TrustedDevice::query()->update(['expires_at' => now()->addDay()]);
        $dentist->forceFill(['status' => 'suspended'])->save();
        $this->withCookie(TrustedDevices::COOKIE, $token)->get('/pasien')->assertRedirect('/login');
    }

    public function test_google_login_trusts_device_only_when_chosen(): void
    {
        [$dentist] = $this->dentistWithWorkspace();
        $google = (new SocialiteUser)->map(['id' => 'g-1', 'email' => $dentist->email, 'name' => $dentist->name]);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->withSession(['trust_device' => false])->get('/auth/google/callback')->assertRedirect();
        $this->assertSame(0, TrustedDevice::count());

        $this->endSession();
        $this->withSession(['trust_device' => true])->withHeader('User-Agent', self::MAC)->get('/auth/google/callback')->assertRedirect();
        $this->assertSame(['Mac/iPad · Chrome'], TrustedDevice::pluck('name')->all());
    }

    public function test_devices_page_lists_and_revokes_own_devices_only(): void
    {
        [$dentist] = $this->dentistWithWorkspace();
        [$other] = $this->dentistWithWorkspace();
        $phone = $this->loginOn($dentist, self::IPHONE);
        $this->loginOn($dentist, self::MAC);
        $this->loginOn($other, self::MAC);
        $otherDevice = TrustedDevice::where('user_id', $other->id)->sole();

        $this->withCookie(TrustedDevices::COOKIE, $phone)->get('/akun/perangkat')->assertOk()
            ->assertSee('iPhone · Safari')->assertSee('Mac/iPad · Chrome')->assertSee('Perangkat ini');
        $this->withCookie(TrustedDevices::COOKIE, $phone)->delete("/akun/perangkat/{$otherDevice->id}")->assertNotFound();

        $this->withCookie(TrustedDevices::COOKIE, $phone)->delete('/akun/perangkat/lainnya')->assertRedirect();
        $this->assertSame(['iPhone · Safari'], TrustedDevice::where('user_id', $dentist->id)->pluck('name')->all());
        $this->assertTrue($otherDevice->fresh() !== null);
    }

    public function test_login_page_offers_trust_checked_and_google_carries_choice(): void
    {
        config(['services.google.client_id' => 'x']);

        $this->get('/login')->assertOk()
            ->assertSee('Percayai perangkat ini')
            ->assertSee('name="remember" value="1" checked', false)
            ->assertSee('/auth/google?trust=1', false);
    }
}
