@extends('layouts.base')

@section('title', 'Arsip · '.$patient->name)

@section('body-class', 'has-tab-bar')

@section('body')
    <header class="app-bar">
        @include('partials.back', ['href' => route('patients.show', $patient)])
        <p class="title">Arsip · {{ $patient->name }}</p>
        <span></span>
    </header>

    @if (session('status'))
        <p class="status archive-status" role="status">{{ session('status') }}</p>
    @endif

    @forelse ($sessions as $s)
        <div class="archive-item">
        <a class="archive-row" href="{{ route('sessions.show', $s) }}">
            <span class="date"><span class="d">{{ $s->session_date->format('d') }}</span><span class="m">{{ $s->session_date->translatedFormat('M y') }}</span></span>
            <span>
                <span class="badge badge-stage">{{ $s->stageLabel() }}</span>
                @if ($s->status === 'draft') <span class="badge badge-draft">Draft</span> @endif
                @if ($s->notes) <p class="note">{{ $s->notes }}</p> @endif
            </span>
            <span class="count">{{ $s->photo_count }} foto</span>
        </a>
        <a class="archive-edit icon-btn" href="{{ route('sessions.edit', $s) }}" aria-label="Ubah atau hapus sesi {{ $s->stageLabel() }} {{ $s->session_date->format('d-m-Y') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9l-4-4L4 16v4z"/><path d="m13.5 6.5 4 4"/></svg></a>
        </div>
    @empty
        <div class="empty">
            <p>Belum ada sesi foto.</p>
            <p><a class="btn btn-primary" href="{{ route('sessions.create', $patient) }}">Mulai sesi foto</a></p>
        </div>
    @endforelse

    @include('partials.patient-tabs', ['active' => 'archive'])
@endsection
