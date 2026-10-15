<?php

namespace App\Listeners;

use App\Models\TrustedDevice;
use Illuminate\Auth\Events\PasswordReset;

/** Kata sandi direset (mis. dicurigai bocor): semua perangkat tepercaya dicabut. */
class ForgetDevicesOnPasswordReset
{
    public function handle(PasswordReset $event): void
    {
        TrustedDevice::where('user_id', $event->user->getAuthIdentifier())->delete();
    }
}
