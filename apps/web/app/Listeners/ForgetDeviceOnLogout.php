<?php

namespace App\Listeners;

use App\Support\TrustedDevices;
use Illuminate\Auth\Events\Logout;

/** Keluar = cabut kepercayaan perangkat ini saja; perangkat lain tetap masuk. */
class ForgetDeviceOnLogout
{
    public function handle(Logout $event): void
    {
        TrustedDevices::forget(request());
    }
}
