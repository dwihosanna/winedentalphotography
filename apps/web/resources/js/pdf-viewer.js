// Penampil PDF di dalam aplikasi (report, rontgen PDF). PWA iPhone membuka PDF menutupi aplikasi tanpa tombol
// kembali, jadi PDF dirender dengan PDF.js di halaman yang punya bar atas + tombol Bagikan.
// Build "legacy": membawa polyfill (core-js) untuk Safari lama. Build standar memakai fitur JS terbaru
// (mis. Map.getOrInsertComputed, Math.sumPrecise) sehingga gagal dimuat di iPad Safari 18.x.
import * as pdfjs from 'pdfjs-dist/legacy/build/pdf.min.mjs';
import workerUrl from 'pdfjs-dist/legacy/build/pdf.worker.min.mjs?url';

pdfjs.GlobalWorkerOptions.workerSrc = workerUrl;

const config = JSON.parse(document.getElementById('pdf-config').textContent);
const pages = document.querySelector('[data-pages]');
const status = document.querySelector('[data-status]');
const shareBtn = document.querySelector('[data-share]');
let file = null;

load();

async function load() {
  try {
    const response = await fetch(config.url, { credentials: 'same-origin' });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.arrayBuffer();
    file = new File([data], config.fileName, { type: 'application/pdf' });
    shareBtn.disabled = false;

    // PDF.js memindahkan buffer ke worker; salin agar file untuk Bagikan tetap utuh.
    const pdf = await pdfjs.getDocument({ data: new Uint8Array(data.slice(0)) }).promise;
    status.hidden = true;
    for (let n = 1; n <= pdf.numPages; n++) await renderPage(await pdf.getPage(n), n, pdf.numPages);
  } catch (e) {
    status.textContent = 'PDF gagal ditampilkan. Coba Bagikan / Unduh, atau muat ulang halaman.';
    console.error(e);
  }
}

async function renderPage(page, n, total) {
  // Lebar kanvas = lebar layar × devicePixelRatio (maks. 2,5×) agar tajam dan tetap ringan di HP.
  const width = pages.clientWidth;
  const base = page.getViewport({ scale: 1 });
  const scale = (width / base.width) * Math.min(window.devicePixelRatio || 1, 2.5);
  const viewport = page.getViewport({ scale });

  const canvas = document.createElement('canvas');
  canvas.width = Math.floor(viewport.width);
  canvas.height = Math.floor(viewport.height);
  canvas.style.aspectRatio = `${base.width} / ${base.height}`;
  canvas.setAttribute('aria-label', `Halaman ${n} dari ${total}`);
  pages.append(canvas);
  await page.render({ canvas, canvasContext: canvas.getContext('2d'), viewport }).promise;
}

shareBtn.addEventListener('click', async () => {
  if (!file) return;
  // iOS: menu Bagikan (WhatsApp, Simpan ke File, Cetak). Browser lain tanpa dukungan: unduh langsung.
  if (navigator.canShare?.({ files: [file] })) {
    try {
      await navigator.share({ files: [file], title: config.title });
    } catch (e) {
      if (e.name !== 'AbortError') console.error(e);
    }
    return;
  }
  const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(file), download: config.fileName });
  a.click();
  setTimeout(() => URL.revokeObjectURL(a.href), 10_000);
});
