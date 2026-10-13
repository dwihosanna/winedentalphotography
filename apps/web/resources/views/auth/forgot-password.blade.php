@extends('layouts.guest')

@section('title', 'Lupa kata sandi')

@section('content')
    <form class="form card" method="POST" action="{{ route('password.email') }}">
        @csrf
        <h2>Lupa kata sandi</h2>
        <p class="muted">Masukkan email akun Anda. Kami kirim link untuk membuat kata sandi baru.</p>
        @include('partials.errors')
        <div class="field">
            <label for="email">Email</label>
            <input id="email" class="input" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Kirim link</button>
        <p class="or"><a href="{{ route('login') }}">Kembali ke Masuk</a></p>
    </form>
@endsection
