// Tombol Bagikan foto: [data-share-file] dengan data-url (URL bertanda tangan), data-name (nama file), data-title.
// iOS/Android: menu Bagikan (WhatsApp, Simpan ke Foto/File, AirDrop). Browser tanpa dukungan: unduh langsung.
const cache = new Map();

async function fileFor(button) {
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

for (const button of document.querySelectorAll('[data-share-file]')) {
  // Unduh lebih dulu agar menu Bagikan langsung muncul saat diketuk (iOS mensyaratkan share() dari ketukan).
  fileFor(button).catch(() => {});

  button.addEventListener('click', async () => {
    const label = button.textContent;
    let file;
    try {
      button.disabled = true;
      button.textContent = 'Menyiapkan…';
      file = await fileFor(button);
    } catch {
      cache.delete(button.dataset.url);
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
