// Preview presentasi report before/after: satu slot per slide, foto sebelum & sesudah berdampingan.
// Navigasi: tombol ‹ ›, panah keyboard, geser (swipe), atau chip slot. Slide berikutnya dimuat lebih dulu.
const slides = JSON.parse(document.getElementById('present-slides').textContent);
const $ = (sel, root = document) => root.querySelector(sel);
const sides = ['before', 'after'].map((key) => ({ key, root: $(`[data-side="${key}"]`) }));
const chips = $('[data-chips]');
let index = 0;

for (const [i, s] of slides.entries()) {
  const b = Object.assign(document.createElement('button'), { type: 'button', textContent: s.code, title: s.label });
  b.addEventListener('click', () => show(i));
  chips.append(b);
}

function show(i) {
  index = (i + slides.length) % slides.length;
  const slide = slides[index];
  $('[data-slot-title]').textContent = `${slide.code} · ${slide.label}`;
  $('[data-count]').textContent = `${index + 1} / ${slides.length}`;
  for (const { key, root } of sides) {
    const data = slide[key];
    const img = $('[data-img]', root);
    const loading = $('[data-loading]', root);
    $('[data-empty]', root).hidden = !!data;
    $('[data-caption]', root).textContent = data ? `· ${data.stage} · ${data.date}` : '';
    img.hidden = !data;
    loading.hidden = true;
    if (data) {
      // Tampilkan "Memuat…" hanya bila foto penuh belum ada di cache browser.
      if (img.getAttribute('src') !== data.url) {
        img.style.opacity = 0;
        loading.hidden = false;
        img.onload = () => { img.style.opacity = 1; loading.hidden = true; };
        img.onerror = () => { loading.textContent = 'Foto gagal dimuat. Tutup lalu buka lagi presentasinya.'; };
        img.src = data.url;
      }
      img.alt = `${key === 'before' ? 'Sebelum' : 'Sesudah'}: ${slide.label}`;
    } else {
      img.removeAttribute('src');
    }
  }
  [...chips.children].forEach((c, n) => c.setAttribute('aria-current', String(n === index)));
  chips.children[index]?.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
  preload(slides[(index + 1) % slides.length]);
}

function preload(slide) {
  for (const key of ['before', 'after']) if (slide?.[key]) new Image().src = slide[key].url;
}

$('[data-prev]').addEventListener('click', () => show(index - 1));
$('[data-next]').addEventListener('click', () => show(index + 1));
document.addEventListener('keydown', (e) => {
  if (e.key === 'ArrowRight' || e.key === 'PageDown' || e.key === ' ') { e.preventDefault(); show(index + 1); }
  if (e.key === 'ArrowLeft' || e.key === 'PageUp') { e.preventDefault(); show(index - 1); }
  if (e.key === 'Escape' && !document.fullscreenElement) location.href = $('.bar a').href;
});

// Geser horizontal di area foto.
let start = null;
const stage = $('[data-stage]');
stage.addEventListener('pointerdown', (e) => { start = { x: e.clientX, y: e.clientY }; });
stage.addEventListener('pointerup', (e) => {
  if (!start) return;
  const dx = e.clientX - start.x;
  const dy = e.clientY - start.y;
  start = null;
  if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) show(index + (dx < 0 ? 1 : -1));
});

// Layar penuh (iPad/desktop; iPhone Safari tidak mendukung untuk halaman biasa).
const fsBtn = $('[data-fullscreen]');
if (document.fullscreenEnabled) {
  fsBtn.hidden = false;
  fsBtn.addEventListener('click', () => (document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()));
}

// Jaga layar tetap menyala selama presentasi.
let wakeLock = null;
async function keepAwake() {
  try { wakeLock = await navigator.wakeLock?.request('screen'); } catch { /* tidak didukung / ditolak */ }
}
// Wake lock lepas otomatis saat tab disembunyikan; minta lagi saat kembali.
document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible' && (!wakeLock || wakeLock.released)) keepAwake(); });
keepAwake();

show(0);
