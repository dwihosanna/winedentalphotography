@extends('layouts.base')

@section('title', 'Arsip · '.$patient->name)

@section('body-class', 'has-tab-bar')

@section('body')
    <header class="app-bar">
        @include('partials.back', ['href' => route('patients.show', $patient)])
        <p class="title">Arsip · {{ $patient->name }}</p>
        <span></span>
    </header>

    @forelse ($sessions as $s)
        <a class="archive-row" href="{{ route('sessions.show', $s) }}">
            <span class="date"><span class="d">{{ $s->session_date->format('d') }}</span><span class="m">{{ $s->session_date->translatedFormat('M y') }}</span></span>
            <span>
                <span class="badge badge-stage">{{ $s->stageLabel() }}</span>
                @if ($s->status === 'draft') <span class="badge badge-draft">Draft</span> @endif
                @if ($s->notes) <p class="note">{{ $s->notes }}</p> @endif
            </span>
            <span class="count">{{ $s->photo_count }} foto</span>
        </a>
    @empty
        <div class="empty">
            <p>Belum ada sesi foto.</p>
            <p><a class="btn btn-primary" href="{{ route('sessions.create', $patient) }}">Mulai sesi foto</a></p>
        </div>
    @endforelse

    @include('partials.patient-tabs', ['active' => 'archive'])
@endsection
