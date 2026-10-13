{{-- Sheet pasien (FR-02). Wajib: nama + tanggal lahir + salah satu kontak. --}}
@include('partials.errors')

@if ($duplicates = session('duplicates'))
    <div class="notice warning" role="alert">
        <p>Sudah ada pasien dengan nama dan tanggal lahir yang sama:</p>
        <ul>
            @foreach ($duplicates as $dup)
                <li><a href="{{ route('patients.show', $dup['id']) }}">{{ $dup['name'] }} · {{ $dup['dpid'] }}</a></li>
            @endforeach
        </ul>
        <label class="check"><input type="checkbox" name="confirm_duplicate" value="1"> <span>Ini pasien berbeda, tetap simpan.</span></label>
    </div>
@endif

<div class="field">
    <label for="name">Nama pasien</label>
    <input id="name" class="input" name="name" value="{{ old('name', $patient->name) }}" autocomplete="off" required>
</div>
<div class="field">
    <label for="birth_date">Tanggal lahir</label>
    <input id="birth_date" class="input" type="date" name="birth_date" value="{{ old('birth_date', $patient->birth_date?->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required>
</div>
<div class="field">
    <label for="phone">No. HP</label>
    <input id="phone" class="input" type="tel" name="phone" value="{{ old('phone', $patient->phone) }}" inputmode="tel" autocomplete="off">
</div>
<div class="field">
    <label for="email">Email</label>
    <input id="email" class="input" type="email" name="email" value="{{ old('email', $patient->email) }}" autocomplete="off">
    <span class="hint">Isi no. HP atau email (minimal salah satu). Hanya data kontak; aplikasi tidak mengirim apa pun ke pasien.</span>
</div>
<div class="field">
    <label for="rm">No. RM (opsional)</label>
    <input id="rm" class="input" name="rm" value="{{ old('rm', $rm) }}" autocomplete="off">
</div>
<div class="field">
    <label for="sex">Jenis kelamin (opsional)</label>
    <select id="sex" class="select" name="sex">
        <option value="">—</option>
        <option value="L" @selected(old('sex', $patient->sex) === 'L')>Laki-laki</option>
        <option value="P" @selected(old('sex', $patient->sex) === 'P')>Perempuan</option>
    </select>
</div>
