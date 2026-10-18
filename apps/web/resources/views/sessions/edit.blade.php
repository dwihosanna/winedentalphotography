@extends('layouts.base')

@section('title', 'Ubah sesi · '.$patient->name)

@section('body')
    <header class="app-bar">
        @include('partials.back', ['href' => route('sessions.index', $patient)])
        <p class="title">Ubah sesi · {{ $patient->name }}</p>
        <span></span>
    </header>

    <div class="page">
        <form class="form" method="POST" action="{{ route('sessions.update', $session) }}">
            @csrf
            @method('PUT')
            @include('partials.errors')

            <div class="field">
                <label for="stage">Tahap</label>
                <select id="stage" class="select" name="stage">
                    @foreach ($stages as $stage)
                        <option value="{{ $stage->value }}" @selected(old('stage', $session->stage->value) === $stage->value)>{{ $stage->label() }}</option>
                    @endforeach
                </select>
                <span class="hint">Foto tetap di slotnya; status lengkap/draft dihitung ulang sesuai slot wajib tahap baru. Nomor kontrol otomatis mengikuti urutan tanggal.</span>
            </div>
            <div class="field">
                <label for="session_date">Tanggal sesi</label>
                <input id="session_date" class="input" type="date" name="session_date" value="{{ old('session_date', $session->session_date->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required>
            </div>
            <div class="field">
                <label for="notes">Keterangan</label>
                <textarea id="notes" class="input" name="notes" rows="3" maxlength="1000">{{ old('notes', $session->notes) }}</textarea>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Simpan perubahan</button>
        </form>

        <div class="card danger-zone">
            @if ($deletable)
                <form method="POST" action="{{ route('sessions.destroy', $session) }}" onsubmit="return confirm('Hapus sesi {{ $session->stageLabel() }} tanggal {{ $session->session_date->format('d-m-Y') }}?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger btn-block" type="submit">Hapus sesi</button>
                </form>
            @else
                <button class="btn btn-danger btn-block" type="button" disabled>Hapus sesi</button>
                <p class="hint">Sesi yang sudah berisi foto atau dipakai report tidak bisa dihapus (rekam medis disimpan 25 tahun). Bila salah, ubah tahap atau tanggalnya.</p>
            @endif
        </div>
    </div>
@endsection
