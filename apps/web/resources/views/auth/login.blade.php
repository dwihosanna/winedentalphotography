@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
    <form class="form card" method="POST" action="{{ route('login') }}">
        @csrf
        <h2>Masuk</h2>
        @include('partials.errors')
        <div class="field">
            <label for="email">Email</label>
            <input id="email" class="input" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        </div>
        <div class="field">
            <label for="password">Kata sandi</label>
            <input id="password" class="input" type="password" name="password" autocomplete="current-password" required>
        </div>
        <label class="check"><input type="checkbox" name="remember" value="1"> <span>Ingat saya di HP ini</span></label>
        <button class="btn btn-primary btn-block" type="submit">Masuk</button>
        @include('partials.google-button')
        <div class="row-actions">
            <a href="{{ route('password.request') }}">Lupa kata sandi?</a>
            <a href="{{ route('register') }}">Daftar akun dokter</a>
        </div>
    </form>
@endsection
