@extends('layouts.base')

@section('title', 'Sesi foto baru')

@php
    use App\Enums\CaseKind;
    use App\Enums\Stage;

    $firstCase = $cases->first();
    $defaultCase = old('ortho_case_id', $firstCase?->id ?? 'new');
    $defaultKind = old('case_kind', CaseKind::Ortho->value);
@endphp

@section('body')
    <header class="app-bar">
        @include('partials.back', ['href' => route('patients.show', $patient)])
        <p class="title">Sesi foto · {{ $patient->name }}</p>
        <span></span>
    </header>

    <form class="page form" method="POST" action="{{ route('sessions.store', $patient) }}" data-session-form>
        @csrf
        @include('partials.errors')

        @if ($cases->isEmpty() && ! $canCreateCase)
            <div class="notice warning">Pasien ini belum punya kasus. Minta dokter membuat kasus dulu.</div>
        @else
            <div class="field">
                <label for="ortho_case_id">Kasus</label>
                <select id="ortho_case_id" class="select" name="ortho_case_id" data-case-select>
                    @foreach ($cases as $case)
                        <option value="{{ $case->id }}" data-kind="{{ $case->kind->value }}" data-suggest="{{ $case->kind->suggestedStage($case->sessions_count > 0)->value }}" @selected($defaultCase === $case->id)>
                            {{ $case->kind->label() }} · {{ $case->label() }}{{ $case->installed_on ? ' · pasang '.$case->installed_on->format('d-m-Y') : '' }}
                        </option>
                    @endforeach
                    @if ($canCreateCase)
                        <option value="new" @selected($defaultCase === 'new')>Kasus baru…</option>
                    @endif
                </select>
            </div>

            @if ($canCreateCase)
                <fieldset class="form card" data-new-case>
                    <h3>Kasus baru</h3>
                    <div class="field">
                        <span class="label">Jenis kasus</span>
                        <div class="segmented">
                            @foreach (CaseKind::cases() as $kind)
                                <label><input type="radio" name="case_kind" value="{{ $kind->value }}" data-kind-radio @checked($defaultKind === $kind->value)> {{ $kind->label() }}</label>
                            @endforeach
                        </div>
                    </div>

                    <div class="form" data-kind-fields="ortho">
                        <div class="field">
                            <label for="brace_type">Tipe behel</label>
                            <select id="brace_type" class="select" name="brace_type" data-other-select>
                                <option value="">Pilih tipe behel</option>
                                @foreach (config('dental.brace_types') as $type)
                                    <option value="{{ $type }}" @selected(old('brace_type') === $type)>{{ $type }}</option>
                                @endforeach
                                <option value="other" @selected(old('brace_type') === 'other')>Lainnya…</option>
                            </select>
                            <input class="input" name="brace_type_other" value="{{ old('brace_type_other') }}" placeholder="mis. Clear aligner" aria-label="Tipe behel lainnya" data-other-input hidden>
                        </div>
                        <div class="field">
                            <label for="installed_on">Tanggal pemasangan (opsional)</label>
                            <input id="installed_on" class="input" type="date" name="installed_on" value="{{ old('installed_on') }}" max="{{ now()->format('Y-m-d') }}">
                        </div>
                    </div>

                    <div class="form" data-kind-fields="general">
                        <div class="field">
                            <label for="treatment_type">Jenis perawatan</label>
                            <select id="treatment_type" class="select" name="treatment_type" data-other-select>
                                <option value="">Pilih jenis perawatan</option>
                                @foreach (config('dental.treatment_types') as $type)
                                    <option value="{{ $type }}" @selected(old('treatment_type') === $type)>{{ $type }}</option>
                                @endforeach
                                <option value="other" @selected(old('treatment_type') === 'other')>Lainnya…</option>
                            </select>
                            <input class="input" name="treatment_type_other" value="{{ old('treatment_type_other') }}" placeholder="mis. Perawatan saluran akar" aria-label="Jenis perawatan lainnya" data-other-input hidden>
                        </div>
                    </div>
                </fieldset>
            @endif

            <div class="field">
                <label for="stage">Tahap</label>
                <select id="stage" class="select" name="stage" data-stage-select data-old="{{ old('stage') }}">
                    @foreach (CaseKind::cases() as $kind)
                        <optgroup label="{{ $kind->label() }}" data-kind="{{ $kind->value }}">
                            @foreach ($kind->stages() as $stage)
                                <option value="{{ $stage->value }}" @selected(old('stage') === $stage->value)>{{ $stage->label() }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <span class="hint" data-kind-hint="ortho">Awal: wajib foto wajah + intraoral. Tahap lain: wajib intraoral. Nomor kontrol diisi otomatis.</span>
                <span class="hint" data-kind-hint="general">Semua slot opsional: foto bagian yang dirawat saja (mis. intraoral atau FREE untuk close-up).</span>
            </div>
            <div class="field">
                <label for="session_date">Tanggal sesi</label>
                <input id="session_date" class="input" type="date" name="session_date" value="{{ old('session_date', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required>
                <span class="hint">Untuk foto lama, isi tanggal kunjungan saat foto diambil.</span>
            </div>
            <div class="field">
                <label for="notes">Keterangan (opsional)</label>
                <input id="notes" class="input" name="notes" value="{{ old('notes') }}" placeholder="mis. ganti kawat 0.016 NiTi / tambalan gigi 11">
            </div>
            <button class="btn btn-primary btn-block" type="submit">Buat sesi</button>
        @endif
    </form>
@endsection

@push('scripts')
    <script>
        // Isian kasus baru hanya bila "Kasus baru…" dipilih; isian dan tahap mengikuti jenis kasus.
        const form = document.querySelector('[data-session-form]');
        const caseSelect = form?.querySelector('[data-case-select]');
        const stageSelect = form?.querySelector('[data-stage-select]');
        const box = form?.querySelector('[data-new-case]');
        let userPickedStage = !!stageSelect?.dataset.old;

        function currentKind() {
            if (caseSelect.value !== 'new') return caseSelect.selectedOptions[0].dataset.kind;
            return form.querySelector('[data-kind-radio]:checked')?.value ?? 'ortho';
        }

        function suggestedStage(kind) {
            if (caseSelect.value !== 'new') return caseSelect.selectedOptions[0].dataset.suggest;
            return kind === 'ortho' ? 'initial' : 'before';
        }

        function sync() {
            if (!caseSelect) return;
            const kind = currentKind();
            if (box) box.hidden = caseSelect.value !== 'new';
            for (const el of form.querySelectorAll('[data-kind-fields]')) {
                el.hidden = el.dataset.kindFields !== kind;
                // Isian jenis lain tidak ikut terkirim.
                for (const input of el.querySelectorAll('select, input')) input.disabled = el.hidden || (box?.hidden ?? true);
            }
            for (const el of form.querySelectorAll('[data-kind-hint]')) el.hidden = el.dataset.kindHint !== kind;
            for (const group of stageSelect.querySelectorAll('optgroup')) {
                const off = group.dataset.kind !== kind;
                group.hidden = off;
                for (const opt of group.querySelectorAll('option')) opt.disabled = off;
            }
            if (!userPickedStage || stageSelect.selectedOptions[0]?.disabled) {
                stageSelect.value = suggestedStage(kind);
            }
            syncOther();
        }

        function syncOther() {
            for (const select of form.querySelectorAll('[data-other-select]')) {
                const input = select.parentElement.querySelector('[data-other-input]');
                input.hidden = select.value !== 'other' || select.disabled;
                input.disabled = input.hidden;
            }
        }

        if (caseSelect) {
            caseSelect.addEventListener('change', () => { userPickedStage = false; sync(); });
            form.addEventListener('change', (e) => {
                if (e.target.matches('[data-kind-radio]')) { userPickedStage = false; sync(); }
                if (e.target.matches('[data-other-select]')) syncOther();
                if (e.target === stageSelect) userPickedStage = true;
            });
            sync();
        }
    </script>
@endpush
