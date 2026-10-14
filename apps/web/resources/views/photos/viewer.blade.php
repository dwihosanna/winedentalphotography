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
    <title>{{ $photo->slot_code }} · {{ $patient->name }}</title>
    {{-- Latar gelap netral seperti kamera dan presentasi: warna sekitar memengaruhi penilaian warna gigi/gusi. --}}
    @include('partials.timestamp-pref')
    @vite(['resources/css/present.css', 'resources/js/share-file.js'])
</head>
<body class="photo-viewer" data-no-update-banner>
    <header class="bar">
        <a class="icon" href="{{ $backUrl }}" aria-label="Kembali">‹</a>
        <div class="who">
            <b>{{ $photo->slot_code }} · {{ $slot }}</b>
            <span>{{ $patient->name }} · {{ $photo->session->stageLabel() }} · {{ $photo->session->session_date->format('d-m-Y') }}{{ $variant === 'original' && $photo->edited_path ? ' · foto asli' : '' }}</span>
        </div>
        <button class="icon ts-toggle" type="button" data-timestamp-toggle aria-pressed="true" aria-label="Tampilkan/sembunyikan timestamp (ikut tercetak saat dibagikan)" title="Timestamp">🕒</button>
        <button class="share" type="button" data-share-file data-stamp="{{ $photo->timestampText() }}" data-url="{{ $url }}" data-name="{{ $fileName }}" data-title="{{ $photo->slot_code }} · {{ $patient->name }}">Bagikan</button>
    </header>

    <main class="viewer">
        <div class="frame">
            <img src="{{ $url }}" alt="{{ $slot }}">
            @include('partials.timestamp')
        </div>
    </main>
</body>
</html>
