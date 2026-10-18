@extends('layouts.base')

@section('title', 'Edit '.$photo->slot_code.' · '.$patient->name)

@section('body')
    <header class="app-bar">
        @include('partials.back', ['href' => route('photos.show', $photo)])
        <p class="title">Edit {{ $photo->slot_code }} · {{ $slot['label'] ?? '' }}</p>
        <span></span>
    </header>

    <div class="page page-wide editor" data-editor>
        @include('partials.errors')
        <p class="notice" data-auto-flip-note hidden>Dibalik otomatis karena slot ini biasanya dipotret lewat cermin. Ketuk tombol balik untuk mematikan.</p>

        <div class="editor-stage" data-stage>
            <canvas data-canvas aria-label="Pratinjau edit; cubit untuk zoom, geser foto, tarik sudut bingkai untuk crop"></canvas>
            <p class="muted" data-loading>Memuat foto…</p>
        </div>
        <p class="muted editor-hint">Cubit untuk zoom, geser untuk memindah foto, tarik sudut bingkai untuk crop.</p>

        <div class="editor-tools">
            <button class="btn btn-sm btn-secondary" type="button" data-action="rotate-left" aria-label="Putar kiri 90°">↺ 90°</button>
            <button class="btn btn-sm btn-secondary" type="button" data-action="rotate-right" aria-label="Putar kanan 90°">↻ 90°</button>
            <button class="btn btn-sm btn-secondary" type="button" data-action="flip-h" aria-pressed="false">Balik ↔</button>
            <button class="btn btn-sm btn-secondary" type="button" data-action="flip-v" aria-pressed="false">Balik ↕</button>
        </div>

        <div class="field">
            <label for="straighten">Luruskan <span class="muted" data-straighten-value>0°</span></label>
            <input id="straighten" type="range" min="-{{ $config['maxStraighten'] }}" max="{{ $config['maxStraighten'] }}" step="0.1" value="0" data-straighten>
        </div>

        <div class="editor-tools">
            <span class="muted">Crop:</span>
            <button class="btn btn-sm btn-ghost" type="button" data-aspect="locked" aria-pressed="true">Rasio asli</button>
            <button class="btn btn-sm btn-ghost" type="button" data-aspect="free" aria-pressed="false">Bebas</button>
            <button class="btn btn-sm btn-ghost" type="button" data-action="crop-reset">Tanpa crop</button>
        </div>

        <div class="row-actions">
            <form method="POST" action="{{ route('photos.update', $photo) }}" data-save-form>
                @csrf
                @method('PUT')
                <input type="hidden" name="params" data-params>
                <button class="btn btn-primary" type="submit" data-save disabled>Simpan</button>
            </form>
            @if ($photo->edit_params !== null)
                <form method="POST" action="{{ route('photos.reset', $photo) }}" onsubmit="return confirm('Hapus semua edit dan kembali ke foto asli?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-ghost" type="submit">Reset ke asli</button>
                </form>
            @endif
        </div>
        <p class="muted">File asli tidak pernah diubah; hasil edit disimpan sebagai salinan.</p>
    </div>

    <script type="application/json" id="editor-config">@json($config)</script>
    @vite('resources/js/editor.js')
@endsection
