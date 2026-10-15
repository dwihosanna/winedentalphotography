@if (config('services.google.client_id'))
    <p class="or">atau</p>
    {{-- Pilihan "Percayai perangkat ini" (bila ada di halaman) ikut dibawa ke login Google. --}}
    <a class="btn btn-secondary btn-block" href="{{ route('google.redirect', ['trust' => 1]) }}" data-google-login>Lanjutkan dengan Google</a>
    <script>
        (() => {
            const link = document.querySelector('[data-google-login]');
            const trust = document.querySelector('[data-trust-device]');
            if (!link || !trust) return;
            const sync = () => { link.href = link.href.replace(/trust=\d/, `trust=${trust.checked ? 1 : 0}`); };
            trust.addEventListener('change', sync);
            sync();
        })();
    </script>
@endif
