@extends('layouts.base')

@section('title', 'Report · '.$patient->name)

@section('body')
    <header class="app-bar">
        @include('partials.back', ['href' => route('patients.show', $patient)])
        <p class="title">Report · {{ $patient->name }}</p>
        <span></span>
    </header>

    <div class="page">
        @include('partials.errors')

        @if ($sessions->count() < 2)
            <div class="empty">
                <p>Report butuh minimal dua sesi foto dalam satu kasus.</p>
                <p><a class="btn btn-primary" href="{{ route('sessions.create', $patient) }}">Mulai sesi foto</a></p>
            </div>
        @else
            <form class="form card" method="GET" action="{{ route('reports.create', $patient) }}" data-auto-submit>
                @if ($cases->count() > 1)
                    <div class="field">
                        <label for="case">Kasus</label>
                        <select id="case" class="select" name="case">
                            @foreach ($cases as $c)
                                <option value="{{ $c->id }}" @selected($c->is($case))>{{ $c->kind->label() }} · {{ $c->label() }} · {{ $c->sessions->count() }} sesi{{ $c->installed_on ? ' · '.$c->installed_on->format('m/Y') : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="filter-dates">
                    @foreach (['before' => 'Sebelum', 'after' => 'Sesudah'] as $key => $label)
                        <div class="field">
                            <label for="{{ $key }}">{{ $label }}</label>
                            <select id="{{ $key }}" class="select" name="{{ $key }}">
                                @foreach ($sessions as $s)
                                    <option value="{{ $s->id }}" @selected($s->is($$key))>{{ $s->session_date->format('d-m-Y') }} · {{ $s->stageLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
                <noscript><button class="btn btn-secondary" type="submit">Tampilkan</button></noscript>
            </form>

            <form class="form" method="POST" action="{{ route('reports.store', $patient) }}" data-report-form>
                @csrf
                <input type="hidden" name="ortho_case_id" value="{{ $case->id }}">
                <input type="hidden" name="before_session_id" value="{{ $before->id }}">
                <input type="hidden" name="after_session_id" value="{{ $after->id }}">

                @if ($slots->isEmpty())
                    <p class="empty">Kedua sesi belum punya foto.</p>
                @endif

                <ul class="report-rows">
                    @foreach ($slots as $code => $slot)
                        <li class="report-row">
                            <label class="check">
                                <input type="hidden" name="slots[{{ $code }}][include]" value="0">
                                <input type="checkbox" name="slots[{{ $code }}][include]" value="1" checked>
                                <span><b>{{ $code }}</b> {{ $slot['label'] }}</span>
                            </label>
                            <div class="report-pair">
                                @foreach (['before', 'after'] as $side)
                                    @php($choices = $options[$side]->get($code, collect()))
                                    <div class="report-pick">
                                        <img src="{{ $choices->first()?->thumb_path ? $choices->first()->url('thumb') : '' }}" alt="" @if (! $choices->first()) hidden @endif>
                                        <select class="select" name="slots[{{ $code }}][{{ $side }}]" aria-label="{{ $code }} {{ $side === 'before' ? 'sebelum' : 'sesudah' }}">
                                            @foreach ($choices as $photo)
                                                <option value="{{ $photo->id }}" data-thumb="{{ $photo->thumb_path ? $photo->url('thumb') : '' }}">
                                                    {{ $photo->replaced_by_photo_id ? 'Retake lama · '.$photo->created_at->format('H:i') : 'Terbaru' }}{{ $photo->edited_path ? ' (diedit)' : '' }}
                                                </option>
                                            @endforeach
                                            <option value="">Tanpa foto</option>
                                        </select>
                                    </div>
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ul>

                <label class="check">
                    <input type="hidden" name="show_timestamp" value="0">
                    <input type="checkbox" name="show_timestamp" value="1" checked>
                    <span>Cetak timestamp di foto (tanggal & jam foto diambil). Tidak dicetak pada report anonim.</span>
                </label>

                <label class="check">
                    <input type="hidden" name="anonymized" value="0">
                    <input type="checkbox" name="anonymized" value="1" @disabled(! $canAnonymize) data-locked="{{ $canAnonymize ? '0' : '1' }}">
                    <span>
                        Anonim untuk edukasi/media sosial: sembunyikan nama, DPID, No. RM, tanggal lahir, dan tanggal sesi.
                        @unless ($canAnonymize)
                            <br><span class="muted">Butuh consent publikasi pasien yang masih berlaku.</span>
                        @endunless
                    </span>
                </label>

                <button class="btn btn-primary btn-block" type="submit" data-submit @disabled($slots->isEmpty())>Buat PDF</button>
                <button class="btn btn-secondary btn-block" type="submit" formaction="{{ route('reports.present', $patient) }}" formmethod="get" data-present @disabled($slots->isEmpty())>Case Report Preview</button>
                <p class="muted">Case Report Preview menampilkan foto pilihan di layar penuh untuk dijelaskan langsung ke pasien, tanpa membuat PDF.</p>
            </form>
        @endif

        @if ($reports->isNotEmpty())
            <section>
                <h2 class="list-section">Report sebelumnya</h2>
                <ul class="list">
                    @foreach ($reports as $r)
                        <li><a class="list-row" href="{{ route('reports.show', $r) }}">
                            <span class="row-main"><span class="row-title">{{ $r->title() }}{{ $r->anonymized ? ' · anonim' : '' }}</span><br>
                            <span class="row-sub">{{ $r->created_at->translatedFormat('d M Y H:i') }}</span></span>
                        </a></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        const pick = document.querySelector('[data-auto-submit]');
        pick?.addEventListener('change', (e) => {
            // Ganti kasus → mulai lagi dari sesi bawaan kasus itu.
            if (e.target.name === 'case') pick.querySelectorAll('[name=before],[name=after]').forEach((el) => el.disabled = true);
            pick.submit();
        });
        for (const select of document.querySelectorAll('.report-pick select')) {
            select.addEventListener('change', () => {
                const img = select.previousElementSibling;
                const thumb = select.selectedOptions[0]?.dataset.thumb;
                img.hidden = !thumb;
                if (thumb) img.src = thumb;
            });
        }
        document.querySelector('[data-report-form]')?.addEventListener('submit', (e) => {
            if (e.submitter?.matches('[data-present]')) {
                // Preview memakai GET: token CSRF dan opsi anonim tidak perlu ikut ke URL.
                const skip = e.target.querySelectorAll('[name=_token], [name=anonymized]');
                skip.forEach((el) => el.disabled = true);
                setTimeout(() => skip.forEach((el) => el.disabled = el.type === 'checkbox' && el.dataset.locked === '1'), 0);
                return;
            }
            const btn = e.target.querySelector('[data-submit]');
            btn.disabled = true;
            btn.textContent = 'Membuat PDF…';
        });
    </script>
@endpush
