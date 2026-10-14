@extends('layouts.base')

@section('title', $title)

@section('body-class', 'pdf-page')

@section('body')
    <header class="app-bar">
        @include('partials.back', ['href' => $backUrl])
        <p class="title">{{ $title }}</p>
        <button class="action" type="button" data-share disabled>Bagikan</button>
    </header>

    <main class="pdf-pages" data-pages>
        <p class="muted pdf-status" data-status role="status">Memuat PDF…</p>
    </main>

    <script type="application/json" id="pdf-config">@json(['url' => $pdfUrl, 'fileName' => $fileName, 'title' => $title])</script>
    @vite('resources/js/pdf-viewer.js')
@endsection
