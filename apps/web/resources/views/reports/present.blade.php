<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#111111">
    <link rel="icon" href="/icons/iconnew.svg" type="image/svg+xml">
    <link rel="icon" href="/icons/favicon-32.png" type="image/png" sizes="32x32">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="robots" content="noindex">
    <title>Case Report Preview · {{ $patient->name }}</title>
    {{-- Latar gelap netral (bukan warna merek) agar warna gigi/gusi dinilai apa adanya, sama seperti layar kamera. --}}
    @include('partials.timestamp-pref')
    @vite(['resources/css/present.css', 'resources/js/present.js'])
</head>
<body data-no-update-banner>
    <header class="bar">
        <a class="icon" href="{{ $backUrl }}" aria-label="Tutup Case Report Preview">✕</a>
        <div class="who">
            <b>{{ $patient->name }}</b>
            <span>{{ $title }} · {{ $case->kind->label() }} · {{ $case->label() }}</span>
        </div>
        <button class="icon ts-toggle" type="button" data-timestamp-toggle aria-pressed="true" aria-label="Tampilkan/sembunyikan timestamp" title="Timestamp">🕒</button>
        <span class="count" data-count></span>
        <button class="icon" type="button" data-fullscreen aria-label="Layar penuh" hidden>⛶</button>
    </header>

    <main class="stage" data-stage aria-live="polite">
        <h1 class="slot" data-slot-title></h1>
        <div class="pair">
            @foreach (['before' => 'Sebelum', 'after' => 'Sesudah'] as $side => $label)
                <figure class="side" data-side="{{ $side }}">
                    <div class="frame">
                        <img alt="" data-img>
                        <p class="empty" data-empty hidden>Tidak ada foto</p>
                        <p class="loading" data-loading hidden>Memuat…</p>
                        <span class="ts" data-time hidden></span>
                    </div>
                    <figcaption><b>{{ $label }}</b> <span data-caption></span></figcaption>
                </figure>
            @endforeach
        </div>
        <button class="nav prev" type="button" data-prev aria-label="Slot sebelumnya">‹</button>
        <button class="nav next" type="button" data-next aria-label="Slot berikutnya">›</button>
    </main>

    <nav class="chips" data-chips aria-label="Pilih slot"></nav>

    <script type="application/json" id="present-slides">@json($slides)</script>
</body>
</html>
