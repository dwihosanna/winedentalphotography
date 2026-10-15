@extends('layouts.base')

@section('title', 'Perangkat tepercaya')

@section('body')
    <header class="app-bar">
        @include('partials.back', ['href' => route('patients.index')])
        <p class="title">Perangkat tepercaya</p>
        <span></span>
    </header>

    <div class="page">
        @if (session('status'))
            <p class="status" role="status">{{ session('status') }}</p>
        @endif
        <p class="muted">Perangkat di bawah tetap masuk tanpa login ulang selama {{ config('dental.trusted_device_days') }} hari sejak terakhir dipakai. Keluar di satu perangkat tidak memengaruhi perangkat lain.</p>

        @forelse ($devices as $device)
            <div class="card device-row">
                <div>
                    <b>{{ $device->name }}</b>
                    @if ($device->id === $currentId) <span class="badge badge-success">Perangkat ini</span> @endif
                    <p class="muted">Terakhir dipakai {{ $device->last_used_at->copy()->setTimezone(config('dental.display_timezone'))->translatedFormat('d M Y H:i') }}
                        · berlaku sampai {{ $device->expires_at->copy()->setTimezone(config('dental.display_timezone'))->translatedFormat('d M Y') }}</p>
                </div>
                <form method="POST" action="{{ route('devices.destroy', $device) }}" onsubmit="return confirm('Cabut kepercayaan perangkat ini?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-ghost" type="submit">Cabut</button>
                </form>
            </div>
        @empty
            <p class="empty">Belum ada perangkat tepercaya. Centang "Percayai perangkat ini" saat login.</p>
        @endforelse

        @if ($devices->count() > ($currentId ? 1 : 0))
            <form method="POST" action="{{ route('devices.destroy-others') }}" onsubmit="return confirm('Cabut semua perangkat selain perangkat ini?')">
                @csrf
                @method('DELETE')
                <button class="btn btn-secondary btn-block" type="submit">Cabut semua perangkat lain</button>
            </form>
        @endif
    </div>
@endsection
