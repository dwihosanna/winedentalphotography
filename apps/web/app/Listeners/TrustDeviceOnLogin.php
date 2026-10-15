<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\TrustedDevices;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;

/** Login dengan "Percayai perangkat ini" (remember) → daftarkan perangkat tepercaya. */
class TrustDeviceOnLogin
{
    public function handle(Login $event): void
    {
        // Login otomatis dari cookie remember Laravel juga memicu event ini dengan remember=true: bukan pilihan baru.
        if (! $event->remember || Auth::viaRemember() || ! $event->user instanceof User) {
            return;
        }

        TrustedDevices::issue($event->user, request());
    }
}
