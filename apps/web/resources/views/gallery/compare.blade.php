@extends('layouts.base')

@section('title', 'Bandingkan · '.$patient->name)

@section('body')
    <header class="app-bar">
        @include('partials.back', ['href' => route('gallery.index', $patient)])
        <p class="title">Bandingkan · {{ $patient->name }}</p>
        <span></span>
    </header>

    <div class="page page-wide">
        <form class="form compare-form" method="GET" action="{{ route('gallery.compare', $patient) }}" data-auto-submit>
            <div class="field">
                <label for="slot">Kategori</label>
                <select id="slot" class="select" name="slot">
                    @foreach ($slots as $code => $s)
                        <option value="{{ $code }}" @selected($code === $slotCode)>{{ $code }} · {{ $s['label'] }}</option>
                    @endforeach
                </select>
            </div>
            @if ($b)
                <div class="filter-dates">
                    @foreach (['a' => 'Sebelum', 'b' => 'Sesudah'] as $key => $label)
                        <div class="field">
                            <label for="{{ $key }}">{{ $label }}</label>
                            <select id="{{ $key }}" class="select" name="{{ $key }}">
                                @foreach ($photos as $sessionId => $photo)
                                    <option value="{{ $sessionId }}" @selected($$key->photo_session_id === $sessionId)>{{ $photo->session->session_date->format('d-m-Y') }} · {{ $photo->session->stageLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            @endif
            <noscript><button class="btn btn-primary" type="submit">Tampilkan</button></noscript>
        </form>

        @if ($b)
            <div class="compare">
                @foreach (['Sebelum' => $a, 'Sesudah' => $b] as $label => $photo)
                    <figure>
                        @if ($photo->isImage())
                            <a href="{{ route('photos.show', $photo) }}"><img src="{{ $photo->url($photo->displayVariant()) }}" alt="{{ $label }}: {{ $slot['label'] }}, {{ $photo->session->session_date->format('d-m-Y') }}"></a>
                        @else
                            <a class="ph" href="{{ route('photos.viewer', $photo) }}">PDF</a>
                        @endif
                        <figcaption><b>{{ $label }}</b> · {{ $photo->session->session_date->format('d-m-Y') }} · {{ $photo->session->stageLabel() }}</figcaption>
                    </figure>
                @endforeach
            </div>
        @else
            <div class="empty">
                <p>Perbandingan butuh foto {{ $slot['label'] }} dari minimal dua sesi. Saat ini ada {{ $photos->count() }}.</p>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        // Ganti pilihan → langsung tampilkan. Ganti kategori → mulai lagi dari sesi pertama vs terakhir.
        const form = document.querySelector('[data-auto-submit]');
        form.addEventListener('change', (e) => {
            if (e.target.name === 'slot') form.querySelectorAll('[name=a],[name=b]').forEach((el) => el.disabled = true);
            form.submit();
        });
    </script>
@endpush
