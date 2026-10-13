{{-- Pemakaian akun Free (FR-34): peringatan di 80% dan 100%. --}}
@if ($workspace->patient_limit || $workspace->storage_limit_bytes)
    @php
        $storagePct = $workspace->storageUsedPercent() ?? 0;
        $patientPct = $workspace->patient_limit ? min(100, intdiv($patientCount * 100, $workspace->patient_limit)) : 0;
        $state = fn ($pct) => $pct >= 100 ? 'full' : ($pct >= 80 ? 'warn' : '');
        $gb = fn ($bytes) => number_format($bytes / 1024 ** 3, 1, ',', '.');
    @endphp
    <div class="meter {{ $state($patientPct) }}" style="--value: {{ $patientPct }}%">
        <div class="label"><span>Pasien</span><span>{{ $patientCount }} / {{ $workspace->patient_limit }}</span></div>
        <div class="bar"><div class="fill"></div></div>
    </div>
    <div class="meter {{ $state($storagePct) }}" style="--value: {{ min(100, $storagePct) }}%">
        <div class="label"><span>Penyimpanan</span><span>{{ $gb($workspace->used_bytes) }} / {{ $gb($workspace->storage_limit_bytes) }} GB</span></div>
        <div class="bar"><div class="fill"></div></div>
    </div>
@endif
