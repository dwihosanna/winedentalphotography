// Kamera sesi foto (FR-06): getUserMedia + garis bantu SVG per slot. Di-port dari poc/camera/app.js v2.5
// (dibekukan di tag poc-v0). Bedanya: foto tidak disimpan di HP, tetapi langsung diunggah ke sesi lewat antrean
// di memori (coba ulang otomatis); halaman memperingatkan bila ditutup sebelum antrean kosong.

const $ = (id) => document.getElementById(id);
const el = Object.fromEntries([
  'stage', 'video', 'overlay', 'flash', 'doneBtn', 'slotTitle', 'slotCheck', 'menuBtn', 'level', 'bubble', 'levelText',
  'slotChips', 'zoomBar', 'lastThumb', 'shutter', 'focusBtn', 'mirrorBtn', 'queueBtn', 'startScreen', 'startBtn',
  'startNote', 'panel', 'cameraSelect', 'opacity', 'opacityOut', 'levelBtn', 'toast',
  'lastBox', 'gallery', 'galleryBack', 'galleryTitle', 'galleryClose', 'galleryGrid', 'galleryView', 'galleryImg',
  'galleryPrev', 'galleryNext', 'galleryRetake',
].map((id) => [id, $(id)]));

const config = JSON.parse($('camera-config').textContent);
const csrf = document.querySelector('meta[name="csrf-token"]').content;

const REQUEST_SIZE = [4032, 3024];
const LEVEL_TOLERANCE = { roll: 2, pitch: 3 }; // derajat
const SVG_NS = 'http://www.w3.org/2000/svg';
const PREF_KEY = 'camera-prefs';
const ZOOM_STEPS = [1, 2, 3, 4, 5]; // kelipatan terhadap lensa utama (1×), seperti app Kamera
// Kamera virtual iPhone yang menggabungkan beberapa lensa; iOS memilih lensa sendiri sesuai zoom dan jarak.
const MULTI_CAM = [/triple|tiga/i, /dual wide|lebar ganda|ganda lebar/i, /dual|ganda/i];
// 'main' = hanya lensa utama: zoom = crop digital lensa yang sama, jadi zoom tidak memicu pindah lensa dan fokus ulang.
const LENS_AUTO = { main: 'Default: lensa utama', multi: 'Otomatis: multi-lensa by iOS' };
const LENS_DEFAULT = 'main';
const NOT_MAIN = /front|depan|tele|ultra|wide|lebar|dual|ganda|triple|tiga/i;
const HAS_ULTRA_WIDE = /triple|tiga|dual wide|lebar ganda|ganda lebar/i; // zoom 1 = ultra wide (0,5×)
const RETRY_MS = [2000, 5000, 15000];

const state = {
  slots: config.slots,
  slot: null,
  stream: null,
  track: null,
  cameras: [],
  started: false,
  level: { on: false, roll: null, pitch: null, g: null },
  // Rotasi fisik HP dari sensor gravitasi. rel = selisih dengan rotasi layar (kunci rotasi aktif).
  turn: { sign: 0, hand: 0, phys: null, rel: 0 },
  geometryAt: 0, // waktu terakhir layar/video berputar (capture menunggu sampai stabil)
  lastLandscapeScr: null, // arah landscape layar terakhir (90/-90), untuk bingkai video yang belum ikut berputar
  lastFrameAt: 0, // waktu frame video terakhir tampil (pengawas kamera membeku)
  recovering: false,
  autoDeviceId: null,
  focus: { locked: false, distance: null, mode: null },
  zoomBase: 1,
  zoomBySlot: {},
  mirrorBySlot: {},
  queue: [], // foto yang belum terunggah: { blob, slot, meta, tries, error }
  uploading: false,
  lastUrl: null,
};

/* ---------- Start ---------- */

function init() {
  const prefs = loadPrefs();
  el.opacity.value = prefs.opacity || '0.9';
  state.zoomBySlot = prefs.zoomBySlot || {};
  for (const s of state.slots) state.mirrorBySlot[s.code] = !!s.mirror?.default;
  el.cameraSelect.replaceChildren(...Object.entries(LENS_AUTO).map(([v, t]) => new Option(t, v)));
  el.cameraSelect.value = LENS_AUTO[prefs.lens] ? prefs.lens : LENS_DEFAULT;
  applyOpacity();

  if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
    el.startBtn.disabled = true;
    el.startNote.textContent = 'Kamera hanya bisa dipakai lewat alamat https://. Pakai tombol unggah di bawah.';
    return;
  }

  applyLayoutMode();
  selectSlot(config.start);
  renderChips();
  bind();
}

function bind() {
  el.startBtn.addEventListener('click', onStart);
  el.menuBtn.addEventListener('click', () => el.panel.showModal());
  el.shutter.addEventListener('click', capture);
  el.mirrorBtn.addEventListener('click', () => {
    state.mirrorBySlot[state.slot.code] = !state.mirrorBySlot[state.slot.code];
    applyMirrorPreview();
  });
  el.queueBtn.addEventListener('click', (e) => { e.stopPropagation(); processQueue(true); });
  el.lastBox.addEventListener('click', openGallery);
  el.lastBox.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openGallery(); } });
  el.galleryClose.addEventListener('click', closeGallery);
  el.galleryBack.addEventListener('click', showGalleryGrid);
  el.galleryPrev.addEventListener('click', () => showGalleryPhoto(gallery.index - 1));
  el.galleryNext.addEventListener('click', () => showGalleryPhoto(gallery.index + 1));
  el.galleryRetake.addEventListener('click', () => {
    const item = gallery.items[gallery.index];
    closeGallery();
    if (item) selectSlot(item.slot);
  });
  bindGallerySwipe();
  el.cameraSelect.addEventListener('change', () => { state.autoDeviceId = null; savePrefs(); startCamera(); });
  el.focusBtn.addEventListener('click', () => (state.focus.locked ? unlockFocus() : lockFocus()));
  el.opacity.addEventListener('input', () => { applyOpacity(); savePrefs(); });
  el.levelBtn.addEventListener('click', enableLevel);
  el.doneBtn.addEventListener('click', (e) => {
    if (state.queue.length && !confirm(`${state.queue.length} foto belum terunggah dan akan hilang. Tetap keluar?`)) e.preventDefault();
  });
  window.addEventListener('beforeunload', (e) => {
    if (state.queue.length) { e.preventDefault(); e.returnValue = ''; }
  });
  window.addEventListener('online', () => processQueue(true));
  const geometryChanged = () => {
    state.geometryAt = performance.now();
    applyLayoutMode();
    layoutOverlay();
    setTimeout(checkFrozen, SETTLE_MS + 300); // kamera iOS sering membeku tepat saat HP diputar
  };
  window.addEventListener('resize', geometryChanged);
  screen.orientation?.addEventListener?.('change', geometryChanged);
  el.video.addEventListener('resize', geometryChanged); // dimensi video berubah saat HP diputar
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden && state.started && (!state.track || state.track.readyState === 'ended')) startCamera();
  });
}

async function onStart() {
  el.startScreen.hidden = true;
  if (state.started) return;
  state.started = true;
  // Sensor gerak dipakai untuk waterpass, rotasi garis bantu, dan orientasi foto; iOS hanya mengizinkan dari
  // ketukan pengguna. Ditunggu dulu sebelum kamera: dua dialog izin bersamaan di iOS bisa saling menimpa.
  await enableLevel();
  await startCamera();
  watchFrames();
}

/* ---------- Kamera ---------- */

async function startCamera() {
  stopCamera();
  el.shutter.disabled = true;
  const [w, h] = REQUEST_SIZE;
  const lens = el.cameraSelect.value;
  const auto = !!LENS_AUTO[lens];
  const deviceId = auto ? state.autoDeviceId : lens;
  state.focus = { locked: false, distance: null, mode: null }; // track baru: fokus kembali otomatis
  const video = {
    width: { ideal: w },
    height: { ideal: h },
    ...(deviceId ? { deviceId: { exact: deviceId } } : { facingMode: { ideal: 'environment' } }),
  };

  try {
    state.stream = await navigator.mediaDevices.getUserMedia({ video, audio: false });
  } catch (e) {
    if (auto && state.autoDeviceId) { state.autoDeviceId = null; return startCamera(); } // kembali ke kamera bawaan
    toast(cameraError(e));
    return;
  }

  state.track = state.stream.getVideoTracks()[0];
  state.track.addEventListener('ended', () => toast('Kamera berhenti. Buka Pengaturan lalu pilih kamera lagi, atau muat ulang halaman.'));
  el.video.srcObject = state.stream;
  try { await el.video.play(); } catch { /* autoplay ditangani atribut muted/playsinline */ }
  await waitForVideo();
  await refreshCameras();
  if (auto && !state.autoDeviceId) {
    // Label kamera baru terbaca setelah izin diberikan; pindah ke kamera yang sesuai pilihan otomatis bila ada.
    const cam = lens === 'main' ? findMainCamera() : findMultiCamera();
    if (cam) {
      state.autoDeviceId = cam.deviceId;
      if (cam.deviceId !== state.track.getSettings().deviceId) return startCamera();
    }
  }
  renderControls();
  layoutOverlay();
  el.shutter.disabled = false;
  // Kamera multi-lensa iPhone mulai di ultra wide (0,5×, distorsi); default ke 1× bila slot belum punya zoom.
  await setZoomX(state.zoomBySlot[state.slot?.code] ?? 1, false);
}

function stopCamera() {
  state.stream?.getTracks().forEach((t) => t.stop());
  state.stream = null;
  state.track = null;
}

/* ---------- Galeri foto sesi di layar kamera ----------
   Kamera tetap berjalan di belakang; berpindah halaman akan menghentikan kamera dan bisa menghilangkan antrean unggah.
   Isi: foto di server (URL bertanda tangan) + foto yang masih di antrean unggah (dari memori, ditandai ↑). */
const gallery = { items: [], index: 0 };

async function openGallery() {
  el.gallery.hidden = false;
  showGalleryGrid();
  el.galleryGrid.replaceChildren(Object.assign(document.createElement('p'), { className: 'galleryNote', textContent: 'Memuat foto…' }));
  let server = [];
  try {
    const res = await fetch(config.photosUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (res.ok) server = (await res.json()).photos;
  } catch { /* offline: tampilkan antrean saja */ }
  const pending = state.queue.map((q) => {
    q.previewUrl ??= URL.createObjectURL(q.blob);
    return { slot: q.slot.code, label: q.slot.label, thumb: q.previewUrl, full: q.previewUrl, time: 'mengunggah', pending: true };
  });
  // Foto antrean menggantikan foto server di slot yang sama (retake yang belum terunggah).
  const pendingSlots = new Set(pending.map((p) => p.slot));
  const order = state.slots.map((s) => s.code);
  gallery.items = [...server.filter((p) => !pendingSlots.has(p.slot)), ...pending]
    .sort((a, b) => order.indexOf(a.slot) - order.indexOf(b.slot));
  renderGalleryGrid();
}

function renderGalleryGrid() {
  if (!gallery.items.length) {
    el.galleryGrid.replaceChildren(Object.assign(document.createElement('p'), { className: 'galleryNote', textContent: 'Belum ada foto di sesi ini.' }));
    return;
  }
  el.galleryGrid.replaceChildren(...gallery.items.map((item, i) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = item.pending ? 'pending' : '';
    b.setAttribute('aria-label', `${item.slot} ${item.label}`);
    const img = Object.assign(document.createElement('img'), { src: item.thumb, alt: '', loading: 'lazy' });
    const cap = Object.assign(document.createElement('span'), { textContent: `${item.slot} · ${item.pending ? '↑' : item.time.slice(-5)}` });
    b.append(img, cap);
    b.addEventListener('click', () => showGalleryPhoto(i));
    return b;
  }));
}

function showGalleryGrid() {
  el.galleryView.hidden = true;
  el.galleryGrid.hidden = false;
  el.galleryBack.style.visibility = 'hidden'; // tetap memakan tempat agar judul/Tutup tidak bergeser
  el.galleryTitle.textContent = 'Foto sesi ini';
}

function showGalleryPhoto(i) {
  if (!gallery.items.length) return;
  gallery.index = (i + gallery.items.length) % gallery.items.length;
  const item = gallery.items[gallery.index];
  el.galleryImg.src = item.full;
  el.galleryImg.alt = `${item.slot} ${item.label}`;
  el.galleryTitle.textContent = `${item.slot} · ${item.label}${item.pending ? ' · mengunggah' : ` · ${item.time}`}`;
  el.galleryGrid.hidden = true;
  el.galleryView.hidden = false;
  el.galleryBack.style.visibility = 'visible';
}

function releasePreview(item) {
  if (item?.previewUrl) URL.revokeObjectURL(item.previewUrl);
}

function closeGallery() {
  el.gallery.hidden = true;
  el.galleryImg.removeAttribute('src');
  state.lastFrameAt = performance.now(); // jangan dianggap beku karena galeri menutupi video
}

function bindGallerySwipe() {
  let start = null;
  el.galleryView.addEventListener('pointerdown', (e) => { start = { x: e.clientX, y: e.clientY }; });
  el.galleryView.addEventListener('pointerup', (e) => {
    if (!start) return;
    // Menu diputar (.sideways): ubah arah geser ke koordinat lapisan yang diputar.
    const rel = document.documentElement.classList.contains('sideways') ? state.turn.rel : 0;
    const a = (-rel * Math.PI) / 180;
    const rx = e.clientX - start.x;
    const ry = e.clientY - start.y;
    const dx = rx * Math.cos(a) - ry * Math.sin(a);
    const dy = rx * Math.sin(a) + ry * Math.cos(a);
    start = null;
    if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) showGalleryPhoto(gallery.index + (dx < 0 ? 1 : -1));
  });
}

/* ---------- Pengawas kamera membeku ----------
   Safari iOS kadang berhenti mengirim frame (track tetap "live") saat HP diputar ketika aplikasi sibuk mengolah/
   mengunggah foto: video membeku di frame terakhir. Frame dipantau; bila >1,5 detik tidak ada frame baru, video
   disambung ulang lalu, bila masih beku, kamera dibuka ulang. Capture ditolak selama beku. */
const FROZEN_MS = 1500;

function watchFrames() {
  state.lastFrameAt = performance.now();
  if ('requestVideoFrameCallback' in HTMLVideoElement.prototype) {
    const tick = () => { state.lastFrameAt = performance.now(); el.video.requestVideoFrameCallback(tick); };
    el.video.requestVideoFrameCallback(tick);
  } else {
    el.video.addEventListener('timeupdate', () => { state.lastFrameAt = performance.now(); });
  }
  setInterval(checkFrozen, 1000);
}

function isFrozen() {
  return !!state.track && state.lastFrameAt > 0 && performance.now() - state.lastFrameAt > FROZEN_MS;
}

function checkFrozen() {
  if (!document.hidden && !state.recovering && isFrozen()) recoverCamera();
}

async function recoverCamera() {
  if (state.recovering) return;
  state.recovering = true;
  el.shutter.disabled = true;
  try {
    // 1) Sambung ulang elemen video ke stream yang sama (murah, sering cukup).
    el.video.srcObject = null;
    el.video.srcObject = state.stream;
    await el.video.play().catch(() => {});
    await new Promise((r) => setTimeout(r, 800));
    // 2) Masih beku: buka ulang kamera (zoom/lensa mengikuti pilihan terakhir).
    if (isFrozen()) await startCamera();
  } finally {
    state.lastFrameAt = performance.now();
    state.recovering = false;
    if (state.track) el.shutter.disabled = false;
    layoutOverlay();
  }
}

function waitForVideo() {
  return new Promise((resolve) => {
    if (el.video.videoWidth) return resolve();
    const done = () => { el.video.removeEventListener('loadedmetadata', done); resolve(); };
    el.video.addEventListener('loadedmetadata', done);
    setTimeout(done, 3000);
  });
}

function cameraError(e) {
  const messages = {
    NotAllowedError: 'Izin kamera ditolak. Buka Pengaturan iPhone > Apps > Safari > Kamera, pilih Izinkan, lalu muat ulang halaman.',
    NotFoundError: 'Kamera tidak ditemukan di perangkat ini.',
    OverconstrainedError: 'Kamera tidak mendukung pengaturan ini. Pilih kamera lain di Pengaturan.',
    NotReadableError: 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi itu lalu coba lagi.',
  };
  return messages[e.name] || `Kamera gagal dibuka: ${e.name} ${e.message}`;
}

async function refreshCameras() {
  const devices = (await navigator.mediaDevices.enumerateDevices()).filter((d) => d.kind === 'videoinput');
  state.cameras = devices.map((d) => ({ label: d.label, deviceId: d.deviceId }));
  const chosen = el.cameraSelect.value;
  el.cameraSelect.replaceChildren(
    ...Object.entries(LENS_AUTO).map(([v, t]) => new Option(t, v)),
    ...devices.map((d, i) => new Option(d.label || `Kamera ${i + 1}`, d.deviceId)),
  );
  el.cameraSelect.value = LENS_AUTO[chosen] || devices.some((d) => d.deviceId === chosen) ? chosen : LENS_DEFAULT;
}

/* Lensa utama (wide 1×). iOS: "Back Camera". Android: label sering tidak jelas, jadi pakai kamera belakang bawaan. */
function findMainCamera() {
  return state.cameras.find((c) => /back|belakang|rear/i.test(c.label) && !NOT_MAIN.test(c.label)) || null;
}

function findMultiCamera() {
  for (const re of MULTI_CAM) {
    const cam = state.cameras.find((c) => re.test(c.label) && !/front|depan/i.test(c.label));
    if (cam) return cam;
  }
  return null;
}

function renderControls() {
  const caps = state.track.getCapabilities?.() || {};
  // Zoom kamera multi-lensa iPhone dimulai dari ultra wide (zoom 1 = 0,5×), jadi 1× = zoom 2.
  state.zoomBase = caps.zoom && caps.zoom.min >= 1 && HAS_ULTRA_WIDE.test(state.track.label) ? 2 : 1;
  renderZoomBar();
  renderFocusBtn();
}

/* ---------- Kunci fokus ---------- */
// Hanya tampil bila browser memberi kontrol fokus (umumnya Chrome Android; Safari iOS belum).

function focusSupport() {
  const caps = state.track?.getCapabilities?.() || {};
  const modes = Array.isArray(caps.focusMode) ? caps.focusMode : [];
  return { modes, distance: caps.focusDistance || null, canLock: modes.includes('manual') || modes.includes('single-shot') };
}

function renderFocusBtn() {
  el.focusBtn.hidden = !focusSupport().canLock;
  el.focusBtn.setAttribute('aria-pressed', String(state.focus.locked));
  el.focusBtn.textContent = state.focus.locked ? 'Fokus: terkunci' : 'Kunci fokus';
}

async function lockFocus() {
  const sup = focusSupport();
  if (!sup.canLock) return;
  const d = state.track.getSettings().focusDistance;
  // Paling stabil: manual pada jarak fokus saat ini. Bila jarak tidak tersedia: single-shot (fokus sekali, lalu tahan).
  const constraint = sup.modes.includes('manual') && sup.distance && d != null
    ? { focusMode: 'manual', focusDistance: d }
    : { focusMode: sup.modes.includes('single-shot') ? 'single-shot' : 'manual' };
  if (!(await tryAdvanced(constraint))) { toast('Kamera menolak kunci fokus.'); return; }
  state.focus = { locked: true, distance: constraint.focusDistance ?? null, mode: constraint.focusMode };
  renderFocusBtn();
  toast('Fokus terkunci. Zoom tidak akan mengubah fokus.');
}

async function unlockFocus() {
  const { modes } = focusSupport();
  if (modes.includes('continuous')) await tryAdvanced({ focusMode: 'continuous' });
  state.focus = { locked: false, distance: null, mode: null };
  renderFocusBtn();
}

/* Beberapa kamera Android mengulang fokus setelah zoom; pasang lagi kunci yang sama. */
async function reapplyFocusLock() {
  const f = state.focus;
  if (!f.locked) return;
  await tryAdvanced(f.distance != null ? { focusMode: 'manual', focusDistance: f.distance } : { focusMode: f.mode });
}

async function tryAdvanced(constraint) {
  try {
    await state.track.applyConstraints({ advanced: [constraint] });
    return true;
  } catch {
    return false;
  }
}

function renderZoomBar() {
  const range = state.track?.getCapabilities?.().zoom;
  if (!range) { el.zoomBar.hidden = true; return; }
  const current = state.track.getSettings().zoom ?? range.min;
  const steps = ZOOM_STEPS.filter((x) => {
    const z = x * state.zoomBase;
    return z >= range.min - 1e-6 && z <= range.max + 1e-6;
  });
  el.zoomBar.replaceChildren(...steps.map((x) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.textContent = `${String(x).replace('.', ',')}×`;
    b.setAttribute('aria-pressed', String(Math.abs(current - x * state.zoomBase) < 0.05));
    b.addEventListener('click', () => setZoomX(x));
    return b;
  }));
  el.zoomBar.hidden = steps.length < 2;
}

/* x = kelipatan terhadap lensa utama. remember: simpan sebagai zoom default slot ini. */
async function setZoomX(x, remember = true) {
  const range = state.track?.getCapabilities?.().zoom;
  if (!range) return;
  const z = Math.min(range.max, Math.max(range.min, x * state.zoomBase));
  // Zoom sama: jangan applyConstraints lagi. Di iOS itu menghentikan kamera sejenak (sering tepat saat HP diputar
  // di antara foto berurutan) sehingga layar macet dan tidak ikut berputar.
  const current = state.track.getSettings().zoom;
  if (current == null || Math.abs(current - z) > 0.01) {
    if (!(await tryAdvanced({ zoom: z }))) toast('Kamera menolak pengaturan zoom.');
    await reapplyFocusLock();
  }
  if (remember && state.slot) {
    state.zoomBySlot[state.slot.code] = x;
    savePrefs();
  }
  renderZoomBar();
}

/* ---------- Slot dan overlay ---------- */

function selectSlot(code) {
  state.slot = state.slots.find((s) => s.code === code) || state.slots[0];
  el.slotTitle.textContent = `${state.slot.code} ${state.slot.label}`;
  el.slotCheck.textContent = (state.slot.checklist || []).join('; ');
  for (const b of el.slotChips.children) b.setAttribute('aria-pressed', String(b.dataset.code === state.slot.code));
  el.level.hidden = !(state.level.on && state.slot.level);
  applyMirrorPreview();
  layoutOverlay();
  if (state.focus.locked) unlockFocus(); // slot lain = jarak objek lain; kunci ulang setelah posisi pas
  const saved = state.zoomBySlot[state.slot.code];
  if (saved != null && state.track) setZoomX(saved, false);
}

/* Slot wajib berikutnya yang masih kosong, lalu slot kosong mana pun; null bila semua terisi. */
function nextEmptySlot() {
  const i = state.slots.indexOf(state.slot);
  const order = [...state.slots.slice(i + 1), ...state.slots.slice(0, i)];
  return order.find((s) => s.required && !s.filled) || order.find((s) => !s.filled) || null;
}

/* Mode cermin: preview dibalik agar tampak seperti foto akhir; capture memakai balikan yang sama. */
function mirrorAxis() {
  const m = state.slot?.mirror;
  return m && state.mirrorBySlot[state.slot.code] ? m.axis : null;
}

function applyMirrorPreview() {
  const m = state.slot?.mirror;
  el.mirrorBtn.hidden = !m;
  const axis = mirrorAxis();
  el.mirrorBtn.setAttribute('aria-pressed', String(!!axis));
  el.mirrorBtn.textContent = axis ? 'Cermin: aktif' : 'Cermin: mati';
  // Sumbu cermin berlaku pada foto tegak; bila layar tidak ikut berputar, sumbu di layar tertukar.
  const screenAxis = state.turn.rel % 180 && axis ? (axis === 'vertical' ? 'horizontal' : 'vertical') : axis;
  el.video.style.transform = screenAxis === 'vertical' ? 'scaleY(-1)' : screenAxis === 'horizontal' ? 'scaleX(-1)' : '';
}

function renderChips() {
  el.slotChips.replaceChildren(...state.slots.map((s) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.dataset.code = s.code;
    b.setAttribute('aria-pressed', String(state.slot?.code === s.code));
    b.classList.toggle('done', !!s.filled);
    b.classList.toggle('optional', !s.required);
    b.append(Object.assign(document.createElement('span'), { textContent: s.code }));
    b.append(Object.assign(document.createElement('small'), { textContent: s.filled ? '✓' : s.required ? 'wajib' : 'opsional' }));
    b.addEventListener('click', () => selectSlot(s.code));
    return b;
  }));
}

function layoutOverlay() {
  if (!state.slot) return;
  const vw = el.video.videoWidth;
  const vh = el.video.videoHeight;
  if (!vw || !vh) { el.overlay.replaceChildren(); return; }

  // Overlay ditempatkan tepat di atas area gambar video (object-fit: contain), mengikuti object-position
  // (landscape: video rapat ke panel samping, sisa ruang hitam di sisi lain untuk tombol zoom).
  const r = el.stage.getBoundingClientRect();
  const scale = Math.min(r.width / vw, r.height / vh);
  const dw = vw * scale;
  const dh = vh * scale;
  const [px, py] = getComputedStyle(el.video).objectPosition.split(' ').map((v) => parseFloat(v) / 100);
  Object.assign(el.overlay.style, {
    left: `${(r.width - dw) * (Number.isFinite(px) ? px : 0.5)}px`,
    top: `${(r.height - dh) * (Number.isFinite(py) ? py : 0.5)}px`,
    width: `${dw}px`,
    height: `${dh}px`,
  });
  el.overlay.setAttribute('viewBox', `0 0 ${dw} ${dh}`);

  // Garis bantu digambar di frame tegak (sesuai posisi HP), lalu diputar bila layar tidak ikut berputar.
  const rel = state.turn.rel;
  const [uw, uh] = rel % 180 ? [dh, dw] : [dw, dh];
  const g = svg('g', { transform: `translate(${dw / 2} ${dh / 2}) rotate(${rel}) translate(${-uw / 2} ${-uh / 2})` });
  g.append(...drawShapes(state.slot.overlay || [], uw, uh));
  el.overlay.replaceChildren(g);
}

function drawShapes(shapes, w, h) {
  const out = [];
  for (const sh of shapes) {
    let node;
    if (sh.t === 'line') node = svg('line', { x1: sh.x1 * w, y1: sh.y1 * h, x2: sh.x2 * w, y2: sh.y2 * h });
    else if (sh.t === 'ellipse') node = svg('ellipse', { cx: sh.cx * w, cy: sh.cy * h, rx: sh.rx * w, ry: sh.ry * h });
    else if (sh.t === 'rect') node = svg('rect', { x: sh.x * w, y: sh.y * h, width: sh.w * w, height: sh.h * h, rx: 8 });
    else if (sh.t === 'polyline') node = svg('polyline', { points: sh.points.map(([x, y]) => `${x * w},${y * h}`).join(' ') });
    else if (sh.t === 'art') { out.push(drawArt(sh, w, h)); continue; }
    else if (sh.t === 'corners') { out.push(drawCorners(sh, w, h)); continue; }
    else continue;
    node.setAttribute('class', sh.dash ? 'guide dash' : 'guide');
    out.push(node);
    if (sh.label) {
      const text = svg('text', { x: sh.lx * w, y: sh.ly * h, 'text-anchor': sh.anchor || 'start' });
      text.setAttribute('class', 'glabel');
      text.textContent = sh.label;
      out.push(text);
    }
  }
  return out;
}

let maskSeq = 0;
/* Gambar garis bantu (gigi/kepala) di viewBox sendiri, diskalakan proporsional ke dalam 'box'. */
function drawArt(sh, w, h) {
  const [bx, by, bw, bh] = (w > h ? sh.boxLandscape : sh.boxPortrait) || sh.box;
  const root = svg('svg', {
    x: bx * w, y: by * h, width: bw * w, height: bh * h,
    viewBox: sh.viewBox, preserveAspectRatio: 'xMidYMid meet', overflow: 'visible',
  });
  const defs = svg('defs', {});
  root.append(defs);
  const byId = Object.fromEntries(sh.layers.map((l) => [l.id, l]));
  for (const layer of sh.layers) {
    const group = svg('g', {});
    group.setAttribute('class', layer.dash ? 'guide art dash' : 'guide art');
    const cover = layer.maskBy && byId[layer.maskBy];
    if (cover) {
      // Lapisan ini disembunyikan di bawah lapisan lain (gigi bawah di belakang gigi atas).
      const id = `mask${++maskSeq}`;
      const big = { x: -1e5, y: -1e5, width: 2e5, height: 2e5 };
      const mask = svg('mask', { id, maskUnits: 'userSpaceOnUse', ...big });
      mask.append(svg('rect', { ...big, fill: 'white' }));
      for (const d of cover.paths) mask.append(svg('path', { d, fill: 'black', stroke: 'black', 'stroke-width': 2 }));
      defs.append(mask);
      group.setAttribute('mask', `url(#${id})`);
    }
    for (const d of layer.paths) group.append(svg('path', { d, 'vector-effect': 'non-scaling-stroke' }));
    root.append(group);
  }
  return root;
}

function drawCorners(sh, w, h) {
  const [bx, by, bw, bh] = sh.box;
  const x0 = bx * w, y0 = by * h, x1 = (bx + bw) * w, y1 = (by + bh) * h;
  const L = Math.min(w, h) * 0.06;
  const d = `M${x0} ${y0 + L}V${y0}H${x0 + L}M${x1 - L} ${y0}H${x1}V${y0 + L}`
    + `M${x1} ${y1 - L}V${y1}H${x1 - L}M${x0 + L} ${y1}H${x0}V${y1 - L}`;
  const p = svg('path', { d });
  p.setAttribute('class', 'corner');
  return p;
}

function svg(tag, attrs) {
  const node = document.createElementNS(SVG_NS, tag);
  for (const [k, v] of Object.entries(attrs)) node.setAttribute(k, v);
  return node;
}

function applyOpacity() {
  el.overlay.style.opacity = el.opacity.value;
  el.opacityOut.value = `${Math.round(el.opacity.value * 100)}%`;
}

/* ---------- Capture dan unggah ---------- */

// Satu kanvas dipakai ulang lalu dikecilkan setelah dipakai: Safari iOS membatasi total memori kanvas, dan kanvas
// 12 MP baru di tiap foto (±48 MB) yang belum dibersihkan membuat foto berurutan tersendat/macet.
const captureCanvas = document.createElement('canvas');
function releaseCanvas(canvas) {
  canvas.width = 1;
  canvas.height = 1;
}

// Tunggu sampai layar/video selesai berputar (maks. ±0,7 detik) agar foto tidak diambil di tengah rotasi.
const SETTLE_MS = 700;
async function settleGeometry() {
  const wait = (state.geometryAt ?? 0) + SETTLE_MS - performance.now();
  if (wait > 0) await new Promise((r) => setTimeout(r, wait));
}

/* Rotasi foto relatif terhadap bingkai video saat ini, dari posisi fisik HP (gravitasi), bukan dari tampilan.
   Tampilan PWA iOS kadang tidak ikut berputar (atau terlambat) saat foto diambil berurutan; dengan cara ini foto
   intraoral yang dipegang landscape tetap tersimpan landscape. Tanpa sensor gerak: ikuti tampilan seperti biasa. */
function captureTurn(vw, vh) {
  const t = state.turn;
  if (t.phys == null) return t.rel;
  const scr = norm(screenAngle());
  if (Math.abs(scr) === 90) state.lastLandscapeScr = scr;
  // Bingkai landscape padahal layar portrait (rotasi belum selesai): pakai arah landscape terakhir.
  const frame = vw > vh ? (Math.abs(scr) === 90 ? scr : (state.lastLandscapeScr ?? (Math.abs(t.phys) === 90 ? t.phys : 90))) : 0;
  const rel = norm(t.phys - frame);
  return Math.abs(rel) === 90 ? rel : 0; // HP terbalik: abaikan
}

async function capture() {
  const v = el.video;
  if (!state.track || !v.videoWidth) return;

  el.shutter.disabled = true;
  const canvas = captureCanvas;
  try {
    await settleGeometry();
    if (isFrozen()) {
      toast('Kamera sedang dipulihkan, coba lagi sebentar.');
      recoverCamera();
      return;
    }
    const vw = v.videoWidth;
    const vh = v.videoHeight;
    const rel = captureTurn(vw, vh);
    const [w, h] = rel % 180 ? [vh, vw] : [vw, vh]; // foto disimpan tegak sesuai posisi HP
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    const axis = mirrorAxis();
    if (axis === 'horizontal') { ctx.translate(w, 0); ctx.scale(-1, 1); }
    if (axis === 'vertical') { ctx.translate(0, h); ctx.scale(1, -1); }
    ctx.translate(w / 2, h / 2);
    ctx.rotate((-rel * Math.PI) / 180);
    ctx.drawImage(v, -vw / 2, -vh / 2, vw, vh);
    const blob = await new Promise((resolve, reject) =>
      canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('Gagal membuat JPEG'))), 'image/jpeg', 0.95));
    releaseCanvas(canvas);

    const settings = state.track.getSettings();
    const slot = state.slot;
    state.queue.push({
      blob,
      slot,
      meta: {
        lens_mode: LENS_AUTO[el.cameraSelect.value] ? el.cameraSelect.value : 'manual',
        zoom: settings.zoom != null ? Math.round((settings.zoom / state.zoomBase) * 100) / 100 : '',
        device: `${/(iPhone|iPad|Android)/.exec(navigator.userAgent)?.[1] ?? 'Browser'} · ${state.track.label}`,
      },
      tries: 0,
      error: null,
    });
    showLast(blob);
    flash();

    // Tandai terisi (optimistis) lalu lanjut ke slot kosong berikutnya, seperti wizard (SRS 6.4).
    slot.filled = true;
    const next = nextEmptySlot();
    renderChips();
    if (next) selectSlot(next.code);
    else toast('Semua slot terisi. Ketuk Selesai bila foto sudah terunggah.');
    processQueue();
  } catch (e) {
    releaseCanvas(canvas);
    toast(`Foto gagal dibuat: ${e.message}`);
  } finally {
    el.shutter.disabled = false;
  }
}

/* Unggah antrean satu per satu. Gagal jaringan: coba ulang otomatis beberapa kali, lalu tunggu ketukan
   "coba lagi" (atau koneksi kembali). Ditolak server (validasi/kuota): foto dibuang dan slot dikosongkan lagi. */
async function processQueue(manual = false) {
  if (state.uploading) return;
  state.uploading = true;
  if (manual && state.queue[0]) state.queue[0].tries = 0;
  try {
    while (state.queue.length) {
      const item = state.queue[0];
      item.error = null;
      renderQueue();
      try {
        await upload(item);
        releasePreview(state.queue.shift());
      } catch (e) {
        item.error = e.message;
        if (e.permanent) {
          releasePreview(state.queue.shift());
          item.slot.filled = false;
          renderChips();
          toast(`${item.slot.code} tidak tersimpan: ${e.message}`);
          continue;
        }
        if (item.tries >= RETRY_MS.length) break;
        await new Promise((r) => setTimeout(r, RETRY_MS[item.tries++]));
      }
    }
  } finally {
    state.uploading = false;
    renderQueue();
  }
}

async function upload(item) {
  const form = new FormData();
  form.append('file', item.blob, `${item.slot.code}.jpg`);
  form.append('slot_code', item.slot.code);
  form.append('source', 'camera');
  for (const [k, v] of Object.entries(item.meta)) form.append(k, v);

  let res;
  try {
    res = await fetch(config.uploadUrl, { method: 'POST', body: form, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
  } catch {
    throw new Error('Tidak ada koneksi');
  }
  const json = await res.json().catch(() => ({}));
  if (res.ok) return json;
  const error = new Error(json.errors ? Object.values(json.errors)[0]?.[0] : json.message || `HTTP ${res.status}`);
  error.permanent = res.status === 422 || res.status === 403 || res.status === 404; // validasi/kuota: coba ulang tidak menolong
  if (res.status === 419) error.message = 'Sesi login habis. Muat ulang halaman lalu masuk lagi.';
  throw error;
}

function renderQueue() {
  const n = state.queue.length;
  el.queueBtn.hidden = n === 0;
  if (!n) return;
  const failed = !!state.queue[0].error && !state.uploading;
  el.queueBtn.classList.toggle('error', !!failed);
  // Lencana ringkas; kalimat lengkap untuk pembaca layar dan tooltip.
  const label = failed ? `${n} foto belum terunggah, ketuk untuk coba lagi` : `Mengunggah ${n} foto`;
  el.queueBtn.textContent = failed ? `!${n}` : `↑${n}`;
  el.queueBtn.setAttribute('aria-label', label);
  el.queueBtn.title = label;
}

function showLast(blob) {
  if (state.lastUrl) URL.revokeObjectURL(state.lastUrl);
  state.lastUrl = URL.createObjectURL(blob);
  el.lastThumb.src = state.lastUrl;
  el.lastThumb.hidden = false;
}

function flash() {
  el.flash.classList.remove('on');
  void el.flash.offsetWidth; // restart animasi
  el.flash.classList.add('on');
}

/* ---------- Waterpass (sensor gravitasi) ---------- */

async function enableLevel() {
  try {
    if (typeof DeviceMotionEvent !== 'undefined' && typeof DeviceMotionEvent.requestPermission === 'function') {
      const permission = await DeviceMotionEvent.requestPermission();
      if (permission !== 'granted') {
        toast('Izin sensor gerak ditolak: foto landscape hanya benar bila layar ikut berputar. Izinkan lewat Pengaturan Safari, lalu buka ulang kamera.');
        return;
      }
    }
    window.addEventListener('devicemotion', onMotion);
    state.level.on = true;
    el.levelBtn.disabled = true;
    el.levelBtn.textContent = 'Waterpass aktif';
    el.level.hidden = !state.slot?.level;
  } catch (e) {
    toast(`Waterpass tidak bisa diaktifkan: ${e.message}`);
  }
}

let levelFrame = 0;
function onMotion(e) {
  const a = e.accelerationIncludingGravity;
  if (!a || a.x == null) return;
  const k = 0.15; // low-pass filter
  const g = state.level.g || { x: a.x, y: a.y, z: a.z };
  g.x += k * (a.x - g.x);
  g.y += k * (a.y - g.y);
  g.z += k * (a.z - g.z);
  state.level.g = g;

  const mag = Math.hypot(g.x, g.y, g.z) || 1;
  updateTurn(g, mag);
  const landscape = Math.abs(state.turn.phys ?? screenAngle()) === 90;
  const down = landscape ? Math.abs(g.x) : Math.abs(g.y);
  const side = landscape ? g.y : g.x;
  state.level.roll = deg(Math.atan2(side, down));                       // horizon miring
  state.level.pitch = deg(Math.asin(Math.max(-1, Math.min(1, g.z / mag)))); // HP condong maju/mundur

  if (levelFrame) return;
  levelFrame = requestAnimationFrame(() => {
    levelFrame = 0;
    if (el.level.hidden) return;
    const { roll, pitch } = state.level;
    el.level.style.setProperty('--x', String(Math.max(-1, Math.min(1, roll / 10))));
    el.level.classList.toggle('ok', levelOk());
    el.levelText.textContent = `Miring ${Math.abs(roll).toFixed(1)}°, condong ${Math.abs(pitch).toFixed(1)}°`;
  });
}

function levelOk() {
  const { roll, pitch } = state.level;
  if (roll == null) return false;
  return Math.abs(roll) <= LEVEL_TOLERANCE.roll && Math.abs(pitch) <= LEVEL_TOLERANCE.pitch;
}

function screenAngle() {
  return screen.orientation?.angle ?? window.orientation ?? 0;
}

const norm = (a) => ((Math.round(a) % 360) + 540) % 360 - 180; // -180..179

/* Posisi fisik HP dari gravitasi, agar garis bantu tetap tegak walau kunci rotasi iOS aktif.
   Tanda dan arah sumbu gravitasi berbeda antar browser, jadi dikalibrasi saat posisi HP sama dengan layar:
   sign saat portrait (iOS melapor gravitasi, bukan reaksinya), hand saat landscape tanpa kunci rotasi. */
function updateTurn(g, mag) {
  if (Math.hypot(g.x, g.y) < 0.6 * mag) return; // HP hampir datar: posisi tidak jelas, pakai yang lama
  const t = state.turn;
  const raw = deg(Math.atan2(g.x, g.y));
  const scr = norm(screenAngle());
  const quad = (a) => {
    const q = Math.round(a / 90) * 90;
    return Math.abs(norm(a - q)) <= 30 ? norm(q) : null; // zona mati ±15° di antara dua posisi
  };
  const phys = (sign, hand) => quad(hand * raw + (sign > 0 ? 0 : 180));
  if (!t.sign) {
    if (scr !== 0) return;
    const q = phys(1, 1);
    if (q === 0) t.sign = 1;
    else if (q === -180) t.sign = -1;
    else return; // HP miring padahal layar portrait (kunci rotasi?): tunggu
  }
  if (!t.hand && Math.abs(scr) === 90) {
    const q = phys(t.sign, 1);
    if (q === scr) t.hand = 1;
    else if (q === -scr) t.hand = -1;
  }
  const now = phys(t.sign, t.hand || 1);
  if (now == null) return;
  t.phys = now;
  const rel = norm(now - scr);
  const newRel = Math.abs(rel) === 90 ? rel : 0; // HP terbalik: abaikan
  if (newRel !== t.rel) {
    t.rel = newRel;
    applyLayoutMode();
    applyMirrorPreview();
    layoutOverlay();
  }
}

/* Tata letak landscape bila layar landscape, atau bila HP dipegang landscape tetapi tampilan iOS tetap potret
   (kunci rotasi / rotasi tertunda): menu diputar sesuai posisi HP (.sideways), video tidak. */
function applyLayoutMode() {
  const html = document.documentElement;
  const screenLandscape = matchMedia('(orientation: landscape)').matches;
  const sideways = !screenLandscape && Math.abs(state.turn.rel) === 90;
  html.classList.toggle('landscape-ui', screenLandscape || sideways);
  html.classList.toggle('sideways', sideways);
  html.style.setProperty('--rel', String(sideways ? state.turn.rel : 0));
  // Panel di sisi yang berlawanan dengan kamera depan (seperti app Kamera bawaan): kamera depan di kanan = HP dipegang
  // tangan kiri, panel kiri; kamera depan di kiri = tangan kanan, panel kanan. Sudut 90 = HP diputar berlawanan arah
  // jarum jam (bagian atas HP, tempat kamera depan, di kiri).
  const turned = norm(screenAngle() + (sideways ? state.turn.rel : 0));
  html.classList.toggle('panel-right', (screenLandscape || sideways) && turned === 90);
}

/* ---------- Lain-lain ---------- */

function deg(rad) { return (rad * 180) / Math.PI; }

function loadPrefs() {
  try { return JSON.parse(localStorage.getItem(PREF_KEY)) || {}; } catch { return {}; }
}

function savePrefs() {
  try {
    localStorage.setItem(PREF_KEY, JSON.stringify({
      opacity: el.opacity.value,
      zoomBySlot: state.zoomBySlot,
      lens: LENS_AUTO[el.cameraSelect.value] ? el.cameraSelect.value : undefined,
    }));
  } catch { /* abaikan */ }
}

let toastTimer = 0;
function toast(message) {
  el.toast.textContent = message;
  el.toast.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { el.toast.hidden = true; }, 5000);
}

init();
