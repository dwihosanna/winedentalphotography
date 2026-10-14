{{-- Preferensi timestamp foto per perangkat (bawaan: tampil). Dipasang di <head> agar tidak berkedip saat halaman dimuat. --}}
<script>
    (() => {
        const key = 'dp.timestamps';
        const read = () => { try { return localStorage.getItem(key) !== 'off'; } catch { return true; } };
        const apply = (on) => {
            document.documentElement.dataset.timestamps = on ? 'on' : 'off';
            document.querySelectorAll('[data-timestamp-toggle]').forEach((b) => b.setAttribute('aria-pressed', String(on)));
        };
        apply(read());
        document.addEventListener('DOMContentLoaded', () => apply(read()));
        document.addEventListener('click', (e) => {
            if (!e.target.closest('[data-timestamp-toggle]')) return;
            const on = document.documentElement.dataset.timestamps !== 'on';
            try { localStorage.setItem(key, on ? 'on' : 'off'); } catch { /* mode privat: berlaku sampai halaman ditutup */ }
            apply(on);
        });
    })();
</script>
