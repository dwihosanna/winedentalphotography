@extends('layouts.guest')

@section('title', 'Kata sandi baru')

@section('content')
    <form class="form card" method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <h2>Kata sandi baru</h2>
        @include('partials.errors')
        <div class="field">
            <label for="email">Email</label>
            <input id="email" class="input" type="email" name="email" value="{{ old('email', $request->email) }}" autocomplete="username" required>
        </div>
        <div class="field">
            <label for="password">Kata sandi baru</label>
            <input id="password" class="input" type="password" name="password" autocomplete="new-password" required autofocus>
        </div>
        <div class="field">
            <label for="password_confirmation">Ulangi kata sandi</label>
            <input id="password_confirmation" class="input" type="password" name="password_confirmation" autocomplete="new-password" required>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Simpan kata sandi</button>
    </form>
@endsection
