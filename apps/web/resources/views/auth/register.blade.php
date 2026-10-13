@extends('layouts.guest')

@section('title', 'Daftar')

@section('content')
    <form class="form card" method="POST" action="{{ route('register') }}">
        @csrf
        <h2>Daftar akun dokter</h2>
        <p class="muted">Akun baru gratis (Free): maks. 50 pasien dan 5 GB. Premium bisa diajukan setelah daftar.</p>
        @include('partials.errors')
        <div class="field">
            <label for="name">Nama lengkap dengan gelar</label>
            <input id="name" class="input" name="name" value="{{ old('name') }}" placeholder="drg. Nama Dokter" autocomplete="name" required>
        </div>
        <div class="field">
            <label for="practice_name">Nama praktik / klinik</label>
            <input id="practice_name" class="input" name="practice_name" value="{{ old('practice_name') }}" autocomplete="organization" required>
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" class="input" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
            <span class="hint">Link verifikasi dikirim ke email ini.</span>
        </div>
        <div class="field">
            <label for="password">Kata sandi</label>
            <input id="password" class="input" type="password" name="password" autocomplete="new-password" required>
        </div>
        <div class="field">
            <label for="password_confirmation">Ulangi kata sandi</label>
            <input id="password_confirmation" class="input" type="password" name="password_confirmation" autocomplete="new-password" required>
        </div>
        @include('partials.declarations')
        <button class="btn btn-primary btn-block" type="submit">Daftar</button>
        @include('partials.google-button')
        <p class="or">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>
    </form>
@endsection
