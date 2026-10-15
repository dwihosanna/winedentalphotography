<?php

namespace App\Http\Controllers;

use App\Models\TrustedDevice;
use App\Support\TrustedDevices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Daftar perangkat tepercaya milik pengguna; cabut satu (mis. HP hilang) atau semua selain perangkat ini. */
class TrustedDeviceController extends Controller
{
    public function index(Request $request): View
    {
        $devices = TrustedDevice::where('user_id', $request->user()->id)->where('expires_at', '>', now())
            ->orderByDesc('last_used_at')->get();

        return view('account.devices', [
            'devices' => $devices,
            'currentId' => $devices->first(fn ($d) => TrustedDevices::isCurrent($d, $request))?->id,
        ]);
    }

    public function destroy(Request $request, TrustedDevice $device): RedirectResponse
    {
        abort_unless($device->user_id === $request->user()->id, 404);

        if (TrustedDevices::isCurrent($device, $request)) {
            TrustedDevices::forget($request);
        } else {
            $device->delete();
        }

        return back()->with('status', "Perangkat {$device->name} tidak lagi tepercaya.");
    }

    public function destroyOthers(Request $request): RedirectResponse
    {
        TrustedDevice::where('user_id', $request->user()->id)->get()
            ->reject(fn ($d) => TrustedDevices::isCurrent($d, $request))
            ->each->delete();

        return back()->with('status', 'Semua perangkat lain tidak lagi tepercaya. Perangkat itu perlu login ulang setelah sesinya habis.');
    }
}
