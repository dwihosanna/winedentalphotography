<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title() }}</title>
    {{-- dompdf: CSS 2.1 + tabel; font DejaVu bawaan (mendukung semua huruf Indonesia). --}}
    <style>
        @page { margin: 14mm 12mm 16mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #2b2224; }
        .footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7pt; color: #8a7d80; }
        .footer .right { float: right; }
        .head { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: bottom; padding: 0; }
        .practice { font-family: 'DejaVu Serif', serif; font-size: 15pt; color: #6B1E2E; }
        .title { text-align: right; font-family: 'DejaVu Serif', serif; font-size: 12pt; color: #6B1E2E; }
        .rule { height: 0; border-top: 1.2pt solid #B8975A; margin: 3mm 0 3mm; }
        .info { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
        .info td { padding: 1mm 2mm 1mm 0; vertical-align: top; width: 25%; }
        .info .k { color: #8a7d80; font-size: 7pt; display: block; }
        .row { width: 100%; border-collapse: collapse; page-break-inside: avoid; margin-bottom: 5mm; }
        .row th { text-align: left; font-weight: bold; font-size: 9pt; padding: 0 0 1.5mm; color: #6B1E2E; }
        .row td { width: 50%; vertical-align: top; text-align: center; padding: 0 1mm; }
        .row img { display: block; margin: 0 auto; }
        .cap { font-size: 7.5pt; color: #5c4f52; padding-top: 1mm; }
        .cap b { color: #2b2224; }
        .empty { height: 30mm; border: 0.6pt dashed #c9bfc1; color: #8a7d80; padding-top: 13mm; }
        .confidential { color: #6B1E2E; }
    </style>
</head>
<body>
    <div class="footer">
        @if ($report->anonymized)
            Untuk edukasi/publikasi dengan consent pasien. Identitas disamarkan.
        @else
            <span class="confidential">Rekam medis, rahasia.</span> Foto adalah bagian rekam medis pasien.
        @endif
        <span class="right">Dibuat {{ now()->translatedFormat('d M Y') }} · Wine Dental Photo</span>
    </div>

    <table class="head">
        <tr>
            <td class="practice">{{ $workspace->practice_name }}</td>
            <td class="title">{{ $report->title() }}</td>
        </tr>
    </table>
    <div class="rule"></div>

    <table class="info">
        <tr>
            @if ($report->anonymized)
                <td><span class="k">Pasien</span>Anonim</td>
                <td><span class="k">Tipe behel</span>{{ $case->brace_type ?: '—' }}</td>
                <td><span class="k">Jarak sesi</span>{{ (int) $before->session_date->diffInMonths($after->session_date) }} bulan</td>
                <td><span class="k">Dokter</span>{{ $case->dentist?->name ?? '—' }}</td>
            @else
                <td><span class="k">Pasien</span>{{ $patient->name }}</td>
                <td><span class="k">Dental Photo ID</span>{{ $patient->dpid }}</td>
                <td><span class="k">No. RM</span>{{ $rm ?? '—' }}</td>
                <td><span class="k">Tanggal lahir</span>{{ $patient->birth_date->format('d-m-Y') }}</td>
            @endif
        </tr>
        @unless ($report->anonymized)
            <tr>
                <td><span class="k">Tipe behel</span>{{ $case->brace_type ?: '—' }}</td>
                <td><span class="k">Dipasang</span>{{ $case->installed_on?->format('d-m-Y') ?? '—' }}</td>
                <td><span class="k">Dilepas</span>{{ $case->removed_on?->format('d-m-Y') ?? '—' }}</td>
                <td><span class="k">Dokter</span>{{ $case->dentist?->name ?? '—' }}</td>
            </tr>
        @endunless
        <tr>
            <td colspan="2"><span class="k">Sebelum</span>{{ $before->stageLabel() }}@unless ($report->anonymized) · {{ $before->session_date->format('d-m-Y') }}@endunless</td>
            <td colspan="2"><span class="k">Sesudah</span>{{ $after->stageLabel() }}@unless ($report->anonymized) · {{ $after->session_date->format('d-m-Y') }}@endunless</td>
        </tr>
    </table>

    @foreach ($rows as $row)
        <table class="row">
            <tr><th colspan="2">{{ $row['code'] }} · {{ $row['label'] }}</th></tr>
            <tr>
                @foreach (['before' => 'Sebelum', 'after' => 'Sesudah'] as $side => $label)
                    <td>
                        @if ($image = $row[$side])
                            <img src="{{ $image['src'] }}" style="width: {{ $image['width_mm'] }}mm; height: {{ $image['height_mm'] }}mm;" alt="">
                            <div class="cap"><b>{{ $label }}</b> · {{ $image['stage'] }}@unless ($report->anonymized) · {{ $image['date'] }}@endunless</div>
                        @else
                            <div class="empty">{{ $label }}: tidak ada foto</div>
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>
    @endforeach
</body>
</html>
