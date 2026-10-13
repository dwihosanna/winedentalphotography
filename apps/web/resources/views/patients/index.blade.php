@extends('layouts.base')

@section('title', 'Pasien')

@section('body')
    <header class="app-bar">
        <details class="menu">
            <summary class="icon-btn" aria-label="Menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></summary>
            <div class="menu-panel">
                <p><b>{{ $workspace->practice_name }}</b><br><span class="muted">{{ auth()->user()->email }}</span></p>
                @include('partials.usage')
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-secondary btn-block" type="submit">Keluar</button>
                </form>
            </div>
        </details>
        <p class="title">
            {{ auth()->user()->name }}
            <span class="badge badge-{{ $workspace->plan->value }}" style="color:inherit">{{ ucfirst($workspace->plan->value) }}</span>
        </p>
        @can('create', App\Models\Patient::class)
            <a class="icon-btn" href="{{ route('patients.create') }}" aria-label="Tambah pasien"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></a>
        @else
            <span></span>
        @endcan
        <form class="search" method="GET" action="{{ route('patients.index') }}" role="search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="6"/><path d="m20 20-4.5-4.5"/></svg>
            <input name="q" value="{{ $q }}" placeholder="Cari nama, DPID, No. RM, no. HP" aria-label="Cari pasien" autocomplete="off" data-patient-search>
        </form>
    </header>

    @if (session('status'))
        <div class="page"><p class="status" role="status">{{ session('status') }}</p></div>
    @endif

    {{-- Petunjuk pasang ke layar utama untuk Safari iPhone (resources/js/pwa.js). --}}
    <div class="page" data-install-hint hidden>
        <div class="notice">
            <p>Pasang aplikasi di layar utama: ketuk <b>Bagikan</b> di Safari, lalu <b>Tambah ke Layar Utama</b>.</p>
            <p><button class="btn btn-sm btn-ghost" type="button">Tutup</button></p>
        </div>
    </div>

    @if ($groups->isEmpty() && ! $q)
        <div class="empty">
            <p>Belum ada pasien.</p>
            @can('create', App\Models\Patient::class)
                <p><a class="btn btn-primary" href="{{ route('patients.create') }}">Tambah pasien</a></p>
            @endcan
        </div>
    @else
        <ul class="list">
            @foreach ($groups as $letter => $patients)
                <li class="list-section" id="huruf-{{ $letter }}" data-section="{{ $letter }}">{{ $letter }}</li>
                @foreach ($patients as $patient)
                    <li data-search="{{ $searchText($patient) }}" data-letter="{{ $letter }}">
                        <a class="list-row" href="{{ route('patients.show', $patient) }}">
                            <span class="avatar">{{ $patient->initials() }}</span>
                            <span class="row-main">
                                <span class="row-title">{{ $patient->displayName() }}</span><br>
                                <span class="row-sub">{{ $patient->birth_date->format('d-m-Y') }} · {{ $patient->is_demo ? 'Contoh, tidak dihitung kuota' : $patient->dpid }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            @endforeach
        </ul>
        <div class="empty" data-search-empty @if (! $groups->isEmpty()) hidden @endif>
            <p>Pasien tidak ditemukan.</p>
            @can('create', App\Models\Patient::class)
                <p><a class="btn btn-primary" href="{{ route('patients.create') }}">Tambah pasien</a></p>
            @endcan
        </div>
        <nav class="alpha-index" aria-label="Indeks huruf">
            @foreach ($groups->keys() as $letter)
                <a href="#huruf-{{ $letter }}">{{ $letter }}</a>
            @endforeach
        </nav>
    @endif
@endsection

@push('scripts')
    @vite('resources/js/patients.js')
@endpush
