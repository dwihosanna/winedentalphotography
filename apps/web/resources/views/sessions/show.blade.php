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
            @if ($photos->isNotEmpty())
                <form method="POST" action="{{ route('photos.destroy-all', $session) }}" onsubmit="return confirm('Hapus SEMUA foto di sesi ini ({{ $photos->count() }} slot)? Foto terhapus permanen dan tidak bisa dikembalikan.')">
                    @csrf
                    @method('DELETE')
                    <button class="icon-btn icon-btn-danger" type="submit" aria-label="Hapus semua foto" title="Hapus semua foto"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg></button>
                </form>
            @endif
        </div>

        <ul class="slot-grid">
            @foreach ($slots as $slot)
                @php($slotPhotos = $photos->get($slot->slot_code, collect()))
                @php($photo = $slotPhotos->last())
                @php($full = $slotPhotos->count() >= $slot->max_photos)
                <li class="slot {{ $photo ? 'filled' : '' }} {{ $slot->required ? 'required' : '' }}">
                    @if ($slot->holdsMultiple())
                        {{-- Slot FREE: sampai max_photos foto, masing-masing bisa dihapus sendiri. --}}
                        <div class="slot-multi">
                            @foreach ($slotPhotos as $i => $p)
                                <div class="slot-multi-item">
                                    <a class="ts-host" href="{{ route('photos.show', $p) }}"><img src="{{ $p->url($p->thumb_path ? 'thumb' : $p->displayVariant()) }}" alt="{{ $slot->label }} {{ $i + 1 }}/{{ $slot->max_photos }}" loading="lazy"></a>
                                    <form method="POST" action="{{ route('photos.destroy', $p) }}" onsubmit="return confirm('Hapus foto {{ $slot->slot_code }} {{ $i + 1 }}/{{ $slot->max_photos }}? Foto terhapus permanen dan tidak bisa dikembalikan.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="icon-btn icon-btn-sm icon-btn-danger" type="submit" aria-label="Hapus foto {{ $slot->slot_code }} {{ $i + 1 }}/{{ $slot->max_photos }}" title="Hapus foto"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg></button>
                                    </form>
                                </div>
                            @endforeach
                            @for ($i = $slotPhotos->count(); $i < $slot->max_photos; $i++)
                                <span class="ph">{{ $i + 1 }}/{{ $slot->max_photos }}</span>
                            @endfor
                        </div>
                    @elseif ($photo && $photo->thumb_path)
                        <a class="ts-host" href="{{ route('photos.show', $photo) }}"><img src="{{ $photo->url('thumb') }}" alt="{{ $slot->label }}" loading="lazy">@include('partials.timestamp')</a>
                    @elseif ($photo)
                        <a class="ph" href="{{ route('photos.viewer', $photo) }}">PDF</a>
                    @else
                        <span class="ph">{{ $slot->slot_code }}</span>
                    @endif
                    <div class="slot-info">
                        <b>{{ $slot->slot_code }}</b> {{ $slot->label }}@if ($slot->holdsMultiple()) · {{ $slotPhotos->count() }}/{{ $slot->max_photos }}@endif
                        <span class="muted">{{ $slot->required ? 'Wajib' : 'Opsional' }}@if ($photo && $photo->exifDateMismatch()) · <span class="warn-text">tanggal foto {{ $photo->exif_taken_at->format('d-m-Y') }}</span>@endif</span>
                    </div>
                    <div class="slot-actions">
                        @if ($slot->holdsMultiple() && $full)
                            <span class="muted">Penuh. Hapus satu foto untuk menambah.</span>
                        @else
                            @if ($slot->kind === 'photo')
                                <a class="btn btn-sm btn-secondary" href="{{ route('sessions.camera', ['session' => $session, 'slot' => $slot->slot_code]) }}">{{ $photo && ! $slot->holdsMultiple() ? 'Ulang' : 'Kamera' }}</a>
                            @endif
                            <form method="POST" action="{{ route('photos.store', $session) }}" enctype="multipart/form-data" data-auto-upload>
                                @csrf
                                <input type="hidden" name="slot_code" value="{{ $slot->slot_code }}">
                                <input type="hidden" name="source" value="upload">
                                <label class="btn btn-sm btn-ghost">
                                    <span>Unggah</span>
                                    <input class="visually-hidden" type="file" name="file" accept="{{ $slot->kind === 'xray' ? 'image/jpeg,image/png,application/pdf' : 'image/jpeg,image/png' }}">
                                </label>
                                <noscript><button class="btn btn-sm" type="submit">Kirim</button></noscript>
                            </form>
                        @endif
                        @if ($photo && ! $slot->holdsMultiple())
                            <form method="POST" action="{{ route('photos.destroy', $photo) }}" onsubmit="return confirm('Hapus foto {{ $slot->slot_code }}? Foto terhapus permanen dan tidak bisa dikembalikan.')">
                                @csrf
                                @method('DELETE')
                                <button class="icon-btn icon-btn-sm icon-btn-danger" type="submit" aria-label="Hapus foto {{ $slot->slot_code }}" title="Hapus foto"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg></button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <p><a class="btn btn-secondary btn-block" href="{{ route('sessions.edit', $session) }}">Ubah tahap, tanggal, atau keterangan sesi</a></p>
    </div>
@endsection

@push('scripts')
    <script>
        // Unggah per slot: kirim begitu file dipilih. Hanya teks label yang diganti: input file ada di dalam label.
        for (const form of document.querySelectorAll('[data-auto-upload]')) {
            form.querySelector('input[type=file]').addEventListener('change', (e) => {
                if (!e.target.files.length) return;
                form.querySelector('label span').textContent = 'Mengunggah…';
                form.submit();
            });
        }
    </script>
@endpush
