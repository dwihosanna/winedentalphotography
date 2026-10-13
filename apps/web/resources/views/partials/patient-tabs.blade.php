{{-- Bar bawah sheet pasien (SRS 5.4): Galeri, + mulai sesi foto, Arsip. --}}
<nav class="tab-bar">
    <a class="tab" href="{{ route('gallery.index', $patient) }}" @if (($active ?? null) === 'gallery') aria-current="page" @endif><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 16 5-5 4 4 3-3 6 6"/></svg>Galeri</a>
    <a class="fab" href="{{ route('sessions.create', $patient) }}" aria-label="Mulai sesi foto"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></a>
    <a class="tab" href="{{ route('sessions.index', $patient) }}" @if (($active ?? null) === 'archive') aria-current="page" @endif><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v10h14V9M10 13h4"/></svg>Arsip</a>
</nav>
