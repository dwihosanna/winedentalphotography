@extends('layouts.base')

@section('title', $patient->name)

@section('body-class', 'has-tab-bar')

@section('body')
    <header class="app-bar">
        <a class="icon-btn" href="{{ route('patients.index') }}" aria-label="Kembali"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg></a>
        <p class="title">Sheet pasien</p>
        @can('update', $patient)
            <a class="action" href="{{ route('patients.edit', $patient) }}">Ubah</a>
        @else
            <span></span>
        @endcan
    </header>

    @if (session('status'))
        <div class="page"><p class="status" role="status">{{ session('status') }}</p></div>
    @endif

    <section class="profile">
        <span class="avatar avatar-lg">{{ $patient->initials() }}</span>
        <p class="name">{{ $patient->displayName() }}</p>
        <button class="id-tag" type="button" data-copy="{{ $patient->dpid }}" aria-label="Salin DPID">{{ $patient->dpid }}</button>
    </section>
    <dl class="details">
        <div><dt>Tanggal lahir</dt><dd>{{ $patient->birth_date->format('d-m-Y') }}</dd></div>
        <div><dt>Email</dt><dd>{{ $patient->email ?: '—' }}</dd></div>
        <div><dt>No. HP</dt><dd>{{ $patient->phone ?: '—' }}</dd></div>
        <div><dt>No. RM</dt><dd>{{ $patient->externalIds->firstWhere('system', config('dental.default_rm_system'))?->value ?? '—' }}</dd></div>
        <div><dt>Jenis kelamin</dt><dd>{{ ['L' => 'Laki-laki', 'P' => 'Perempuan'][$patient->sex] ?? '—' }}</dd></div>
    </dl>

    {{-- Galeri, sesi foto, dan Arsip menyusul di langkah berikutnya. --}}
    <nav class="tab-bar">
        <span class="tab" aria-disabled="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 16 5-5 4 4 3-3 6 6"/></svg>Galeri</span>
        <span class="fab" aria-disabled="true" aria-label="Mulai sesi foto" style="opacity:.45"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span>
        <span class="tab" aria-disabled="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v10h14V9M10 13h4"/></svg>Arsip</span>
    </nav>
@endsection

@push('scripts')
    <script>
        for (const b of document.querySelectorAll('[data-copy]')) {
            b.addEventListener('click', async () => {
                try { await navigator.clipboard.writeText(b.dataset.copy); b.textContent = 'Disalin'; setTimeout(() => { b.textContent = b.dataset.copy; }, 1500); } catch { /* abaikan */ }
            });
        }
    </script>
@endpush
