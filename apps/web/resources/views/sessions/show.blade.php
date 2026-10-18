@extends('layouts.base')

@section('title', $session->stageLabel().' · '.$patient->name)

@section('body')
    <header class="app-bar">
        @include('partials.back', ['href' => route('sessions.index', $patient)])
        <p class="title">{{ $patient->name }}</p>
        <span></span>
    </header>

    <div class="page">
        @if (session('status'))
            <p class="status" role="status">{{ session('status') }}</p>
        @endif
        @include('partials.errors')

        <div class="row-actions">
            <div>
                <span class="badge badge-stage">{{ $session->stageLabel() }}</span>
                @if ($session->status === 'complete')
                    <span class="badge badge-success">Lengkap</span>
                @else
                    <span class="badge badge-draft">Draft · {{ count($missing) }} slot wajib kosong</span>
                @endif
                <p class="muted">{{ $session->session_date->translatedFormat('d F Y') }}</p>
                @if ($session->notes)
                    <p class="muted session-notes">{{ $session->notes }}</p>
                @endif
            </div>
        </div>

        <div class="row-actions">
            <a class="btn btn-primary" href="{{ route('sessions.camera', $session) }}">Buka kamera</a>
            <a class="btn btn-secondary" href="{{ route('sessions.upload', $session) }}">Unggah banyak foto</a>
            @include('partials.timestamp-toggle')
        </div>

        <ul class="slot-grid">
            @foreach ($slots as $slot)
                @php($photo = $photos->get($slot->slot_code))
                <li class="slot {{ $photo ? 'filled' : '' }} {{ $slot->required ? 'required' : '' }}">
                    @if ($photo && $photo->thumb_path)
                        <a class="ts-host" href="{{ route('photos.show', $photo) }}"><img src="{{ $photo->url('thumb') }}" alt="{{ $slot->label }}" loading="lazy">@include('partials.timestamp')</a>
                    @elseif ($photo)
                        <a class="ph" href="{{ route('photos.viewer', $photo) }}">PDF</a>
                    @else
                        <span class="ph">{{ $slot->slot_code }}</span>
                    @endif
                    <div class="slot-info">
                        <b>{{ $slot->slot_code }}</b> {{ $slot->label }}
                        <span class="muted">{{ $slot->required ? 'Wajib' : 'Opsional' }}@if ($photo && $photo->exifDateMismatch()) · <span class="warn-text">tanggal foto {{ $photo->exif_taken_at->format('d-m-Y') }}</span>@endif</span>
                    </div>
                    <div class="slot-actions">
                        @if ($slot->kind === 'photo')
                            <a class="btn btn-sm btn-secondary" href="{{ route('sessions.camera', ['session' => $session, 'slot' => $slot->slot_code]) }}">{{ $photo ? 'Ulang' : 'Kamera' }}</a>
                        @endif
                        <form method="POST" action="{{ route('photos.store', $session) }}" enctype="multipart/form-data" data-auto-upload>
                            @csrf
                            <input type="hidden" name="slot_code" value="{{ $slot->slot_code }}">
                            <input type="hidden" name="source" value="upload">
                            <label class="btn btn-sm btn-ghost">
                                Unggah
                                <input class="visually-hidden" type="file" name="file" accept="{{ $slot->kind === 'xray' ? 'image/jpeg,image/png,application/pdf' : 'image/jpeg,image/png' }}">
                            </label>
                            <noscript><button class="btn btn-sm" type="submit">Kirim</button></noscript>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>

        <p><a class="btn btn-secondary btn-block" href="{{ route('sessions.edit', $session) }}">Ubah tahap, tanggal, atau keterangan sesi</a></p>
    </div>
@endsection

@push('scripts')
    <script>
        // Unggah per slot: kirim begitu file dipilih.
        for (const form of document.querySelectorAll('[data-auto-upload]')) {
            form.querySelector('input[type=file]').addEventListener('change', (e) => {
                if (!e.target.files.length) return;
                form.querySelector('label').textContent = 'Mengunggah…';
                form.submit();
            });
        }
    </script>
@endpush
