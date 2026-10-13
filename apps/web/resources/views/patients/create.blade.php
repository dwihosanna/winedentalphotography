@extends('layouts.base')

@section('title', 'Tambah pasien')

@section('body')
    <header class="app-bar">
        <a class="icon-btn" href="{{ route('patients.index') }}" aria-label="Kembali"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg></a>
        <p class="title">Tambah pasien</p>
        <span></span>
    </header>
    <form class="page form" method="POST" action="{{ route('patients.store') }}">
        @csrf
        @include('patients._form')
        <button class="btn btn-primary btn-block" type="submit">Simpan pasien</button>
    </form>
@endsection
