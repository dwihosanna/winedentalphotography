{{-- Foto profil pasien (EO-02 pertama) atau inisial bila belum ada foto. --}}
@if ($patient->avatarPhoto?->thumb_path)
    <img class="{{ $class }} avatar-photo" src="{{ $patient->avatarPhoto->url('thumb') }}" alt="" loading="lazy">
@else
    <span class="{{ $class }}">{{ $patient->initials() }}</span>
@endif
