{{--
    Kunci zoom cubit/ketuk-dua-kali hanya saat aplikasi dipasang di Layar Utama (PWA), agar tidak terganggu saat memotret
    dan menggeser. Di Safari biasa zoom tetap bisa (aksesibilitas). Halaman penampil foto/PDF tidak memakai partial ini.
--}}
<script>
    (() => {
        const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
        if (!standalone) return;
        const meta = document.querySelector('meta[name="viewport"]');
        if (meta && !/user-scalable/.test(meta.content)) meta.content += ', maximum-scale=1, user-scalable=no';
        document.documentElement.classList.add('zoom-locked');
        const stop = (e) => e.preventDefault();
        // Safari mengabaikan user-scalable=no; event gesture* (khusus Safari) dan sentuhan dua jari diblok langsung.
        for (const type of ['gesturestart', 'gesturechange', 'gestureend']) document.addEventListener(type, stop, { passive: false });
        document.addEventListener('touchmove', (e) => { if (e.touches.length > 1) e.preventDefault(); }, { passive: false });
    })();
</script>
