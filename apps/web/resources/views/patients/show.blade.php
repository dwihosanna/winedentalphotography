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

    @can('report', $patient)
        <div class="page"><a class="btn btn-secondary btn-block" href="{{ route('reports.create', $patient) }}">Buat report before/after</a></div>
    @endcan

    @include('partials.patient-tabs')
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
