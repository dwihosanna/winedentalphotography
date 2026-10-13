{{-- Pernyataan dokter dan syarat layanan (FR-27); disimpan di terms_acceptances. --}}
<label class="check">
    <input type="checkbox" name="dentist_declaration" value="1" @checked(old('dentist_declaration')) required>
    <span>Saya dokter gigi yang memiliki izin praktik (STR) yang berlaku.</span>
</label>
<label class="check">
    <input type="checkbox" name="terms" value="1" @checked(old('terms')) required>
    <span>Saya menyetujui syarat layanan dan pemrosesan data pasien (versi {{ config('dental.terms_version') }}).</span>
</label>
