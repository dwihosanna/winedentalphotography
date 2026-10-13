@extends('layouts.guest')

@section('title', 'Verifikasi email')

@section('content')
    <div class="form card">
        <h2>Verifikasi email</h2>
        <p>Kami sudah mengirim link verifikasi ke <b>{{ auth()->user()->email }}</b>. Buka link itu sebelum menambah pasien.</p>
        @if (session('status') === 'verification-link-sent')
            <p class="status" role="status">Link verifikasi baru sudah dikirim.</p>
        @endif
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button class="btn btn-primary btn-block" type="submit">Kirim ulang link</button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-ghost btn-block" type="submit">Keluar</button>
        </form>
    </div>
@endsection
