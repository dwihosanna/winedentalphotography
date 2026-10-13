<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#000000">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Kamera · {{ $patient->name }}</title>
    {{-- Layar kamera netral (hitam + garis amber), bukan warna merek: warna sekitar memengaruhi penilaian warna gusi/gigi. --}}
    @vite(['resources/css/camera.css', 'resources/js/app.js', 'resources/js/camera.js'])
</head>
{{-- data-no-update-banner: banner versi baru tidak muncul saat memotret (SRS 11.4). --}}
<body data-no-update-banner>
    <main id="stage">
        <video id="video" playsinline muted autoplay></video>
        <svg id="overlay" aria-hidden="true"></svg>
        <div id="flash" aria-hidden="true"></div>
    </main>

    <header id="topbar">
        <a id="doneBtn" class="pill" href="{{ route('sessions.show', $session) }}">Selesai</a>
        <div class="slotHead">
            <h1 id="slotTitle">Memuat slot</h1>
            <p id="slotCheck"></p>
        </div>
        <button id="menuBtn" class="pill" type="button">Pengaturan</button>
    </header>

    <div id="level" hidden>
        <div class="track"><div id="bubble"></div></div>
        <span id="levelText"></span>
    </div>

    <footer id="bottombar">
        <nav id="slotChips" aria-label="Pilih slot foto"></nav>
        <div id="zoomBar" role="group" aria-label="Zoom kamera" hidden></div>
        <div class="shutterRow">
            <div id="lastBox"><img id="lastThumb" alt="Foto terakhir" hidden></div>
            <button id="shutter" type="button" aria-label="Ambil foto" disabled></button>
            <div class="rightCol">
                <button id="focusBtn" type="button" aria-pressed="false" hidden>Kunci fokus</button>
                <button id="mirrorBtn" type="button" aria-pressed="false" hidden>Cermin</button>
                <button id="queueBtn" type="button" hidden></button>
            </div>
        </div>
    </footer>

    <section id="startScreen">
        <div class="startCard">
            <h2>{{ $patient->name }}</h2>
            <p>{{ $session->stageLabel() }} · {{ $session->session_date->format('d-m-Y') }}</p>
            <p class="muted">Garis bantu mengikuti posisi HP. Foto langsung diunggah ke server dan tidak disimpan di galeri HP.</p>
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
