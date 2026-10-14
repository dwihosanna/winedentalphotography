@extends('layouts.base')

@section('title', 'Galeri · '.$patient->name)

@section('body-class', 'has-tab-bar')

@section('body')
    <header class="app-bar">
        @include('partials.back', ['href' => route('patients.show', $patient)])
        <p class="title">Galeri · {{ $patient->name }}</p>
        <span></span>
    </header>

    <div class="page">
        <details class="card" @if ($filtered) open @endif>
            <summary>Filter{{ $filtered ? ' (aktif)' : '' }}</summary>
            <form class="form" method="GET" action="{{ route('gallery.index', $patient) }}">
                <div class="field">
                    <label for="slot">Kategori</label>
                    <select id="slot" class="select" name="slot">
                        <option value="">Semua kategori</option>
                        @foreach ($slots as $code => $s)
                            <option value="{{ $code }}" @selected(($filters['slot'] ?? null) === $code)>{{ $code }} · {{ $s['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="stage">Tahap</label>
                    <select id="stage" class="select" name="stage">
                        <option value="">Semua tahap</option>
                        @foreach (\App\Enums\Stage::cases() as $stage)
                            <option value="{{ $stage->value }}" @selected(($filters['stage'] ?? null) === $stage->value)>{{ $stage->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-dates">
                    <div class="field">
                        <label for="from">Dari tanggal</label>
                        <input id="from" class="input" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
                    </div>
                    <div class="field">
                        <label for="to">Sampai tanggal</label>
                        <input id="to" class="input" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
                    </div>
                </div>
                <div class="row-actions">
                    <button class="btn btn-primary" type="submit">Terapkan</button>
                    @if ($filtered)
                        <a class="btn btn-ghost" href="{{ route('gallery.index', $patient) }}">Hapus filter</a>
                    @endif
                </div>
            </form>
        </details>
        <div class="row-actions">@include('partials.timestamp-toggle')</div>
    </div>

    @forelse ($groups as $code => $photos)
        <section class="gallery-group">
            <h2 class="list-section gallery-head">
                <span>{{ $code }} · {{ $slots[$code]['label'] }} <span class="muted">({{ $photos->count() }})</span></span>
                @if ($photos->count() > 1)
                    <a href="{{ route('gallery.compare', ['patient' => $patient, 'slot' => $code]) }}">Bandingkan</a>
                @endif
            </h2>
            <ul class="gallery-grid">
                @foreach ($photos as $photo)
                    <li>
                        <a href="{{ route('photos.show', $photo) }}">
                            @if ($photo->thumb_path)
                                <img src="{{ $photo->url('thumb') }}" alt="{{ $slots[$code]['label'] }}, {{ $photo->session->session_date->format('d-m-Y') }}" loading="lazy">
                            @else
                                <span class="ph">{{ $photo->isImage() ? $code : 'PDF' }}</span>
                            @endif
                            @include('partials.timestamp')
                            <span class="cap">{{ $photo->session->session_date->format('d-m-y') }} · {{ $photo->session->stageLabel() }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <div class="empty">
            @if ($filtered)
                <p>Tidak ada foto yang cocok dengan filter.</p>
            @else
                <p>Belum ada foto.</p>
                <p><a class="btn btn-primary" href="{{ route('sessions.create', $patient) }}">Mulai sesi foto</a></p>
            @endif
        </div>
    @endforelse

    @include('partials.patient-tabs', ['active' => 'gallery'])
@endsection
