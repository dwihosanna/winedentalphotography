@extends('layouts.base')

@section('title', $report->title().' · '.$patient->name)

@section('body')
    <header class="app-bar">
        @include('partials.back', ['href' => route('reports.create', $patient)])
        <p class="title">{{ $report->title() }}</p>
        <span></span>
    </header>

    <div class="page">
        @if (session('status'))
            <p class="status" role="status">{{ session('status') }}</p>
        @endif

        <a class="btn btn-primary btn-block" href="{{ route('reports.pdf', $report) }}" target="_blank" rel="noopener">Buka PDF</a>
        <a class="btn btn-secondary btn-block" href="{{ route('reports.present', ['patient' => $patient] + $report->presentationQuery()) }}">Preview untuk presentasi</a>

        <dl class="details">
            <div><dt>Pasien</dt><dd>{{ $patient->name }}{{ $report->anonymized ? ' (PDF anonim)' : '' }}</dd></div>
            <div><dt>Sebelum</dt><dd>{{ $report->beforeSession->stageLabel() }} · {{ $report->beforeSession->session_date->format('d-m-Y') }}</dd></div>
            <div><dt>Sesudah</dt><dd>{{ $report->afterSession->stageLabel() }} · {{ $report->afterSession->session_date->format('d-m-Y') }}</dd></div>
            <div><dt>Slot</dt><dd>{{ implode(', ', array_keys($report->selected_photos)) }}</dd></div>
            <div><dt>Dibuat</dt><dd>{{ $report->creator->name }} · {{ $report->created_at->translatedFormat('d M Y H:i') }}</dd></div>
            <div><dt>Ukuran</dt><dd>{{ number_format($report->pdf_bytes / 1048576, 1, ',', '.') }} MB</dd></div>
        </dl>
        <p class="muted">PDF disimpan sebagai snapshot: edit foto setelah ini tidak mengubah report ini. Buat report baru untuk versi terbaru.</p>
    </div>
@endsection
