@extends('layouts.base')

@section('title', 'Sesi foto baru')

@section('body')
    <header class="app-bar">
        @include('partials.back', ['href' => route('patients.show', $patient)])
        <p class="title">Sesi foto · {{ $patient->name }}</p>
        <span></span>
    </header>

    <form class="page form" method="POST" action="{{ route('sessions.store', $patient) }}">
        @csrf
        @include('partials.errors')

        @if ($cases->isEmpty() && ! $canCreateCase)
            <div class="notice warning">Pasien ini belum punya kasus ortodonti. Minta dokter membuat kasus dulu.</div>
        @else
            <div class="field">
                <label for="ortho_case_id">Kasus</label>
                <select id="ortho_case_id" class="select" name="ortho_case_id" data-case-select>
                    @foreach ($cases as $case)
                        <option value="{{ $case->id }}" @selected(old('ortho_case_id') === $case->id)>
                            {{ $case->brace_type }}{{ $case->installed_on ? ' · pasang '.$case->installed_on->format('d-m-Y') : '' }}
                        </option>
                    @endforeach
                    @if ($canCreateCase)
                        <option value="new" @selected(old('ortho_case_id', $cases->isEmpty() ? 'new' : null) === 'new')>Kasus baru…</option>
                    @endif
                </select>
            </div>

            @if ($canCreateCase)
                <fieldset class="form card" data-new-case>
                    <h3>Kasus baru</h3>
                    <div class="field">
                        <label for="brace_type">Tipe behel</label>
                        <input id="brace_type" class="input" name="brace_type" value="{{ old('brace_type') }}" list="brace-types" placeholder="mis. Metal MBT 0.022">
                        <datalist id="brace-types"><option>Metal</option><option>Keramik</option><option>Self-ligating</option><option>Clear aligner</option><option>Lepasan</option></datalist>
                    </div>
                    <div class="field">
                        <label for="installed_on">Tanggal pemasangan (opsional)</label>
                        <input id="installed_on" class="input" type="date" name="installed_on" value="{{ old('installed_on') }}" max="{{ now()->format('Y-m-d') }}">
                    </div>
                </fieldset>
            @endif

            <div class="field">
                <label for="stage">Tahap</label>
                <select id="stage" class="select" name="stage">
                    @foreach (App\Enums\Stage::cases() as $stage)
                        <option value="{{ $stage->value }}" @selected(old('stage', $suggestedStage->value) === $stage->value)>{{ $stage->label() }}</option>
                    @endforeach
                </select>
                <span class="hint">Awal: wajib foto wajah + intraoral. Tahap lain: wajib intraoral. Nomor kontrol diisi otomatis.</span>
            </div>
            <div class="field">
                <label for="session_date">Tanggal sesi</label>
                <input id="session_date" class="input" type="date" name="session_date" value="{{ old('session_date', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required>
                <span class="hint">Untuk foto lama, isi tanggal kunjungan saat foto diambil.</span>
            </div>
            <div class="field">
                <label for="notes">Keterangan (opsional)</label>
                <input id="notes" class="input" name="notes" value="{{ old('notes') }}" placeholder="mis. ganti kawat 0.016 NiTi">
            </div>
            <button class="btn btn-primary btn-block" type="submit">Buat sesi</button>
        @endif
    </form>
@endsection

@push('scripts')
    <script>
        // Tampilkan isian kasus baru hanya bila "Kasus baru…" dipilih.
        const select = document.querySelector('[data-case-select]');
        const box = document.querySelector('[data-new-case]');
        if (select && box) {
            const sync = () => { box.hidden = select.value !== 'new'; };
            select.addEventListener('change', sync);
            sync();
        }
    </script>
@endpush
