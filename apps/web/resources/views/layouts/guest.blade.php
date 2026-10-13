@extends('layouts.base')

@section('body')
    <main class="page auth-page">
        <div class="brand">
            <h1>Dental Photo</h1>
            <p class="muted">Wine Dental Aesthetic</p>
        </div>
        <hr class="divider-gold">
        @if (session('status'))
            <p class="status" role="status">{{ session('status') }}</p>
        @endif
        @yield('content')
    </main>
@endsection
