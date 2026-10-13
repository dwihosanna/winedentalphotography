@extends('layouts.guest')

@section('title', 'Lengkapi akun')

@section('content')
    <form class="form card" method="POST" action="{{ route('onboarding') }}">
        @csrf
        <h2>{{ $needsWorkspace ? 'Lengkapi akun' : 'Syarat layanan diperbarui' }}</h2>
        @if ($needsWorkspace)
            <p class="muted">Akun baru gratis (Free): maks. 50 pasien dan 5 GB.</p>
        @else
            <p class="muted">Setujui syarat layanan versi terbaru untuk melanjutkan.</p>
        @endif
        @include('partials.errors')
        @if ($needsWorkspace)
            <div class="field">
                <label for="practice_name">Nama praktik / klinik</label>
                <input id="practice_name" class="input" name="practice_name" value="{{ old('practice_name') }}" autocomplete="organization" required>
            </div>
        @endif
        @include('partials.declarations')
        <button class="btn btn-primary btn-block" type="submit">Lanjutkan</button>
    </form>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="btn btn-ghost btn-block" type="submit">Keluar</button>
    </form>
@endsection
