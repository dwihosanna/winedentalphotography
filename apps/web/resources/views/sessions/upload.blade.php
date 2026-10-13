@extends('layouts.base')

@section('title', 'Unggah foto')

@section('body')
    <header class="app-bar">
        @include('partials.back', ['href' => route('sessions.show', $session)])
        <p class="title">Unggah · {{ $session->stageLabel() }}</p>
        <span></span>
    </header>

    <div class="page" data-bulk-upload>
        <p class="muted">{{ $patient->name }} · sesi {{ $session->session_date->format('d-m-Y') }}. Pilih banyak foto sekaligus (mis. foto lama dari galeri atau DSLR), lalu tetapkan slot tiap foto.</p>
        <label class="btn btn-secondary btn-block">
            Pilih foto
            <input class="visually-hidden" type="file" accept="image/jpeg,image/png,application/pdf" multiple data-files>
        </label>
        <div class="notice warning" data-conflict hidden>Ada dua file untuk slot yang sama. File terakhir akan menggantikan yang sebelumnya.</div>
        <ul class="upload-list" data-list></ul>
        <button class="btn btn-primary btn-block" type="button" data-submit hidden>Unggah semua</button>
        <a class="btn btn-ghost btn-block" href="{{ route('sessions.show', $session) }}" data-done hidden>Kembali ke sesi</a>
    </div>

    <script type="application/json" id="upload-config">@json(['slots' => $slots, 'uploadUrl' => route('photos.store', $session)])</script>
@endsection

@push('scripts')
    @vite('resources/js/upload.js')
@endpush
