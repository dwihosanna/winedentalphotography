// Tombol Bagikan foto: [data-share-file] dengan data-url (URL bertanda tangan), data-name (nama file), data-title,
// dan data-stamp (watermark: nama depan pasien - sesi - tanggal). Bila timestamp sedang ditampilkan (tombol 🕒), file yang
// dibagikan ikut memuat watermark di pojok kanan bawah, sama seperti foto di PDF report. Dicetak di perangkat, bukan di server.
// iOS/Android: menu Bagikan (WhatsApp, Simpan ke Foto/File, AirDrop). Browser tanpa dukungan: unduh langsung.
const cache = new Map();

// Batas piksel kanvas Safari iOS ±16,7 juta; foto 48 MP diperkecil agar tetap bisa dicetak.
const MAX_CANVAS_PIXELS = 16_000_000;

const stampOn = () => document.documentElement.dataset.timestamps === 'on';

function original(button) {
  const { url, name } = button.dataset;
  if (!cache.has(url)) {
    cache.set(url, fetch(url, { credentials: 'same-origin' }).then(async (r) => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      const blob = await r.blob();
      return new File([blob], name, { type: blob.type || 'image/jpeg' });
    }));
  }
  return cache.get(url);
}

function stamped(button) {
  const key = `${button.dataset.url}|stamp`;
  if (!cache.has(key)) cache.set(key, original(button).then((file) => drawStamp(file, button.dataset.stamp)));
  return cache.get(key);
}

/** File yang akan dibagikan sesuai pilihan timestamp saat ini; disiapkan lebih dulu agar Bagikan langsung muncul. */
function fileFor(button) {
  return button.dataset.stamp && stampOn() ? stamped(button) : original(button);
}

async function drawStamp(file, text) {
  const src = URL.createObjectURL(file);
  try {
    const img = new Image();
    img.src = src;
    await img.decode(); // orientasi EXIF sudah diterapkan browser saat menggambar
    const scale = Math.min(1, Math.sqrt(MAX_CANVAS_PIXELS / (img.naturalWidth * img.naturalHeight)));
    const w = Math.round(img.naturalWidth * scale);
    const h = Math.round(img.naturalHeight * scale);
    const canvas = Object.assign(document.createElement('canvas'), { width: w, height: h });
    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0, w, h);

    // Ukuran dan posisi sama dengan App\Support\PhotoStamp (PDF): ±2,4% lebar foto, pojok kanan bawah.
    let size = Math.max(14, Math.round(w * 0.024));
    const font = (px) => `bold ${px}px -apple-system, "Helvetica Neue", Arial, sans-serif`;
    ctx.font = font(size);
    // Teks panjang: perkecil huruf agar teks muat dalam ±85% lebar foto (sisa untuk latar dan jarak tepi).
    const full = ctx.measureText(text).width;
    if (full > w * 0.85) {
      size = Math.max(8, Math.floor((size * w * 0.85) / full));
      ctx.font = font(size);
    }
    const pad = Math.round(size * 0.5);
    ctx.textBaseline = 'alphabetic';
    const m = ctx.measureText(text);
    const textHeight = Math.ceil(m.actualBoundingBoxAscent + m.actualBoundingBoxDescent) || size;
    const x = Math.max(2 * pad, w - m.width - 3 * pad);
    const baseline = h - 3 * pad;
    ctx.fillStyle = 'rgb(0 0 0 / 0.6)';
    ctx.fillRect(x - pad, baseline - textHeight - pad, m.width + 2 * pad, textHeight + 2 * pad);
    ctx.fillStyle = '#fff';
    ctx.fillText(text, x, baseline - Math.ceil(m.actualBoundingBoxDescent || 0));

    const blob = await new Promise((resolve, reject) =>
      canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('Gagal membuat JPEG'))), 'image/jpeg', 0.92));
    return new File([blob], file.name.replace(/\.(png|jpe?g)$/i, '.jpg'), { type: 'image/jpeg' });
  } finally {
    URL.revokeObjectURL(src);
  }
}

const buttons = [...document.querySelectorAll('[data-share-file]')];
const prepare = () => buttons.forEach((b) => fileFor(b).catch(() => {}));

for (const button of buttons) {
  button.addEventListener('click', async () => {
    const label = button.textContent;
    let file;
    try {
      button.disabled = true;
      button.textContent = 'Menyiapkan…';
      file = await fileFor(button);
    } catch {
      cache.clear();
      alert('Foto gagal disiapkan. Muat ulang halaman lalu coba lagi.');
      return;
    } finally {
      button.disabled = false;
      button.textContent = label;
    }

    if (navigator.canShare?.({ files: [file] })) {
      try {
        await navigator.share({ files: [file], title: button.dataset.title });
      } catch (e) {
        if (e.name !== 'AbortError') console.error(e);
      }
      return;
    }
    const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(file), download: file.name });
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 10_000);
  });
}

// Siapkan file sejak halaman dibuka, dan siapkan ulang bila tombol 🕒 Timestamp diubah
// (preferensi sudah diperbarui oleh partials/timestamp-pref sebelum handler ini jalan).
prepare();
document.addEventListener('click', (e) => {
  if (e.target.closest('[data-timestamp-toggle]')) setTimeout(prepare, 0);
});
