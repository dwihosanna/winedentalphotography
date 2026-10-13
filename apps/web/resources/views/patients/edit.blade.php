@extends('layouts.base')

@section('title', 'Ubah pasien')

@section('body')
    <header class="app-bar">
        <a class="icon-btn" href="{{ route('patients.show', $patient) }}" aria-label="Kembali"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg></a>
        <p class="title">Ubah pasien</p>
        <span></span>
    </header>
    <form class="page form" method="POST" action="{{ route('patients.update', $patient) }}">
        @csrf
        @method('PUT')
        <p class="muted">DPID <span class="id-tag">{{ $patient->dpid }}</span> tidak bisa diubah.</p>
        @include('patients._form')
        <button class="btn btn-primary btn-block" type="submit">Simpan perubahan</button>
    </form>
@endsection
