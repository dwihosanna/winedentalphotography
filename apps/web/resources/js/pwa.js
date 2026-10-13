// Daftarkan service worker dan tampilkan banner saat versi baru siap.
// Banner tidak muncul di layar capture (body[data-no-update-banner]) agar sesi foto tidak terpotong.
if ('serviceWorker' in navigator && window.isSecureContext) {
  window.addEventListener('load', async () => {
    try {
      const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
      const offer = (worker) => worker && navigator.serviceWorker.controller && showBanner(worker);

      offer(registration.waiting);
      registration.addEventListener('updatefound', () => {
        const worker = registration.installing;
        worker?.addEventListener('statechange', () => worker.state === 'installed' && offer(worker));
      });

      let reloading = false;
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (!reloading) { reloading = true; location.reload(); }
      });
    } catch { /* PWA opsional; aplikasi tetap jalan tanpa service worker */ }
  });
}

function showBanner(worker) {
  if (document.body.hasAttribute('data-no-update-banner') || document.querySelector('.update-banner')) return;
  const banner = document.createElement('div');
  banner.className = 'update-banner';
  banner.setAttribute('role', 'status');
  banner.innerHTML = '<span>Versi baru tersedia.</span><button class="btn btn-sm btn-primary" type="button">Muat ulang</button>';
  banner.querySelector('button').addEventListener('click', () => worker.postMessage('SKIP_WAITING'));
  document.body.append(banner);
}

// iPhone tidak punya tombol "Install" otomatis: beri petunjuk sekali di Safari bila belum dipasang.
const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
let dismissed = false;
try { dismissed = localStorage.getItem('pwa-install-hint') === 'off'; } catch { /* abaikan */ }

if (isIos && !standalone && !dismissed && document.querySelector('[data-install-hint]')) {
  const hint = document.querySelector('[data-install-hint]');
  hint.hidden = false;
  hint.querySelector('button')?.addEventListener('click', () => {
    hint.hidden = true;
    try { localStorage.setItem('pwa-install-hint', 'off'); } catch { /* abaikan */ }
  });
}
