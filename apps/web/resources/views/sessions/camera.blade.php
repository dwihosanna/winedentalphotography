<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#000000">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/iconnew.svg" type="image/svg+xml">
    <link rel="icon" href="/icons/favicon-32.png" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Kamera · {{ $patient->name }}</title>
    {{-- Layar kamera netral (hitam + garis amber), bukan warna merek: warna sekitar memengaruhi penilaian warna gusi/gigi. --}}
    @include('partials.pwa-zoom-lock')
    @vite(['resources/css/camera.css', 'resources/js/app.js', 'resources/js/camera.js'])
</head>
{{-- data-no-update-banner: banner versi baru tidak muncul saat memotret (SRS 11.4). --}}
<body data-no-update-banner>
    <main id="stage">
        <video id="video" playsinline muted autoplay></video>
        <svg id="overlay" aria-hidden="true"></svg>
        <div id="flash" aria-hidden="true"></div>
    </main>

    {{-- Lapisan menu; diputar bila HP landscape tetapi tampilan iOS tetap potret (lihat .sideways di camera.css). --}}
    <div id="chrome">
    <header id="topbar">
        <a id="doneBtn" class="pill" href="{{ route('sessions.show', $session) }}">Selesai</a>
        <div class="slotHead">
            <h1 id="slotTitle">Memuat slot</h1>
            <p id="slotCheck"></p>
        </div>
        {{-- Kunci orientasi: tampilan dan foto tidak ikut berputar saat HP sedikit miring. --}}
        <button id="orientBtn" class="pill" type="button" aria-pressed="false" title="Ketuk untuk ganti: Potret → Landscape ↺ → Landscape ↻">
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><rect x="7" y="3" width="10" height="18" rx="2"/></svg>
            <span>Potret</span>
        </button>
        <button id="menuBtn" class="pill" type="button" aria-label="Pengaturan" title="Pengaturan">
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        </button>
    </header>

    <div id="level" hidden>
        <div class="track"><div id="bubble"></div></div>
        <span id="levelText"></span>
    </div>

    <footer id="bottombar">
        <nav id="slotChips" aria-label="Pilih slot foto"></nav>
        <div id="zoomBar" role="group" aria-label="Zoom kamera" hidden></div>
        <div class="shutterRow">
            <div id="lastBox" role="button" tabindex="0" aria-label="Buka galeri foto sesi ini" title="Galeri foto sesi ini">
                <span class="galleryIcon" aria-hidden="true">▦</span>
                <img id="lastThumb" alt="Foto terakhir" hidden>
                {{-- Status unggah: lencana kecil di pojok thumbnail (jumlah foto dalam antrean; merah = gagal, ketuk untuk ulang). --}}
                <button id="queueBtn" type="button" hidden></button>
            </div>
            <button id="shutter" type="button" aria-label="Ambil foto" disabled></button>
            <div class="rightCol">
                <button id="focusBtn" type="button" aria-pressed="false" hidden>Kunci fokus</button>
                <button id="mirrorBtn" type="button" aria-pressed="false" hidden>Cermin</button>
            </div>
        </div>
    </footer>

    {{-- Galeri foto sesi ini di atas kamera (kamera tetap berjalan; ikut diputar bersama menu). --}}
    <section id="gallery" hidden aria-label="Galeri foto sesi ini">
        <header class="galleryHead">
            <button id="galleryBack" type="button" style="visibility: hidden">‹ Semua</button>
            <h2 id="galleryTitle">Foto sesi ini</h2>
            <button id="galleryClose" type="button">Tutup</button>
        </header>
        <div id="galleryGrid"></div>
        <div id="galleryView" hidden>
            <div class="frame"><img id="galleryImg" alt=""></div>
            <div class="galleryNav">
                <button id="galleryPrev" type="button" aria-label="Foto sebelumnya">‹</button>
                <button id="galleryRetake" type="button">Foto ulang slot ini</button>
                <button id="galleryNext" type="button" aria-label="Foto berikutnya">›</button>
            </div>
        </div>
    </section>
    </div>

    <section id="startScreen">
        <div class="startCard">
            <h2>{{ $patient->name }}</h2>
            <p>{{ $session->stageLabel() }} · {{ $session->session_date->format('d-m-Y') }}</p>
            <p class="muted">Orientasi foto dikunci: Potret (default) atau Landscape lewat tombol di atas. Foto langsung diunggah ke server dan tidak disimpan di galeri HP.</p>
            <button id="startBtn" class="primary" type="button">Mulai kamera</button>
            <p id="startNote" class="muted"></p>
            <a class="muted" href="{{ route('sessions.upload', $session) }}">Atau unggah foto dari galeri</a>
        </div>
    </section>

    <dialog id="panel">
        <form method="dialog" class="panelHead">
            <h2>Pengaturan kamera</h2>
            <button type="submit">Tutup</button>
        </form>
        <fieldset>
            <label>Lensa / kamera
                <select id="cameraSelect"></select>
            </label>
            <label>
                <span>Opasitas garis bantu <output id="opacityOut"></output></span>
                <input id="opacity" type="range" min="0.2" max="1" step="0.05" value="0.9">
            </label>
            <button id="levelBtn" type="button">Aktifkan waterpass</button>
        </fieldset>
    </dialog>

    <div id="toast" role="status" hidden></div>

    <script type="application/json" id="camera-config">@json($config)</script>
</body>
</html>
