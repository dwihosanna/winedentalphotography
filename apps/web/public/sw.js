// Service worker Dental Photo (NFR-01: PWA installable).
// - Halaman (HTML) selalu dari jaringan dan tidak pernah di-cache: berisi data pasien (SRS Bagian 9).
//   Saat offline ditampilkan /offline.html.
// - Aset build (/build/, nama file ber-hash), ikon, dan font: cache-first.
// - Versi baru menunggu sampai pengguna menekan "Muat ulang" di banner, agar tidak memotong sesi capture
//   (SRS 11.4, strategi update PWA). Naikkan VERSION setiap kali file ini berubah.
const VERSION = 'v1';
const STATIC_CACHE = `static-${VERSION}`;
const PRECACHE = ['/offline.html', '/icons/icon-192.png', '/manifest.webmanifest'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)));
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    for (const key of await caches.keys()) {
      if (key !== STATIC_CACHE) await caches.delete(key);
    }
    await self.clients.claim();
  })());
});

self.addEventListener('message', (event) => {
  if (event.data === 'SKIP_WAITING') self.skipWaiting();
});

function isStatic(url) {
  return url.origin === self.location.origin
    && (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname === '/manifest.webmanifest');
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);

  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
    return;
  }

  if (isStatic(url)) {
    event.respondWith((async () => {
      const cached = await caches.match(request);
      if (cached) return cached;
      const response = await fetch(request);
      if (response.ok) (await caches.open(STATIC_CACHE)).put(request, response.clone());
      return response;
    })());
  }
  // Selain itu (foto, API): langsung ke jaringan, tanpa cache.
});
