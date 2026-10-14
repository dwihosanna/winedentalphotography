@extends('layouts.base')

@section('title', $photo->slot_code.' · '.$patient->name)

@section('body')
    <header class="app-bar">
        @include('partials.back', ['href' => route('gallery.index', $patient)])
        <p class="title">{{ $photo->slot_code }} · {{ $slot['label'] ?? '' }}</p>
        <span></span>
    </header>

    <div class="page">
        @if (session('status'))
            <p class="status" role="status">{{ session('status') }}</p>
        @endif
        @if ($photo->isImage())
            <a href="{{ $photo->url($photo->displayVariant()) }}" target="_blank" rel="noopener"><img class="photo-full" src="{{ $photo->url($photo->displayVariant()) }}" alt="{{ $slot['label'] ?? $photo->slot_code }}"></a>
        @else
            <a class="btn btn-secondary" href="{{ $photo->url('original') }}" target="_blank" rel="noopener">Buka PDF</a>
        @endif

        <div class="row-actions">
            @if ($photo->isImage())
                <a class="btn btn-primary" href="{{ route('photos.edit', $photo) }}">Edit</a>
            @endif
            <a class="btn btn-secondary" href="{{ route('sessions.show', $session) }}">Buka sesi</a>
            <a class="btn btn-ghost" href="{{ route('gallery.compare', ['patient' => $patient, 'slot' => $photo->slot_code, 'b' => $session->id]) }}">Bandingkan</a>
        </div>

        {{-- Metadata otomatis (FR-18), tanpa input manual. --}}
        <dl class="details">
            <div><dt>Pasien</dt><dd>{{ $patient->name }} · {{ $patient->dpid }}</dd></div>
            <div><dt>Kasus</dt><dd>{{ $case->kind->label() }} · {{ $case->label() }}{{ $case->installed_on ? ' · dipasang '.$case->installed_on->format('d-m-Y') : '' }}</dd></div>
            <div><dt>Sesi</dt><dd>{{ $session->stageLabel() }} · {{ $session->session_date->translatedFormat('d F Y') }}</dd></div>
            <div><dt>Kategori</dt><dd>{{ $photo->slot_code }} · {{ $slot['label'] ?? $photo->category }}</dd></div>
            <div><dt>Sumber</dt><dd>{{ $photo->source === 'camera' ? 'Kamera aplikasi' : 'Unggahan' }}{{ $photo->original_name ? ' · '.$photo->original_name : '' }}</dd></div>
            <div><dt>Diambil oleh</dt><dd>{{ $photo->takenBy?->name ?? '—' }} · {{ $photo->taken_at->translatedFormat('d M Y H:i') }}</dd></div>
            @if ($photo->exif_taken_at)
                <div><dt>Tanggal di file</dt><dd @class(['warn-text' => $photo->exifDateMismatch()])>{{ $photo->exif_taken_at->translatedFormat('d M Y H:i') }}</dd></div>
            @endif
            @if ($photo->device)
                <div><dt>Perangkat</dt><dd>{{ $photo->device }}</dd></div>
            @endif
            @if ($photo->lens_mode || $photo->zoom)
                <div><dt>Lensa</dt><dd>{{ $photo->lens_mode ?? '—' }}{{ $photo->zoom ? ' · '.rtrim(rtrim(number_format($photo->zoom, 1), '0'), '.').'×' : '' }}</dd></div>
            @endif
            @if ($photo->overlay_version)
                <div><dt>Versi overlay</dt><dd>{{ $photo->overlay_version }}</dd></div>
            @endif
            @if ($photo->width)
                <div><dt>Ukuran</dt><dd>{{ $photo->width }} × {{ $photo->height }} px · {{ number_format($photo->original_bytes / 1048576, 1, ',', '.') }} MB</dd></div>
            @endif
            @if ($photo->edited_path)
                <div><dt>Edit</dt><dd>{{ $photo->isAutoFlipped() ? 'Dibalik otomatis (foto cermin)' : 'Diedit' }} · <a href="{{ $photo->url('original') }}" target="_blank" rel="noopener">lihat asli</a></dd></div>
            @endif
            @if ($versions > 1)
                <div><dt>Riwayat</dt><dd>{{ $versions - 1 }} foto sebelumnya diganti (retake)</dd></div>
            @endif
        </dl>
    </div>
@endsection
