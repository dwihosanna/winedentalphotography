// PoC kamera ortodonti – Mode A (getUserMedia + overlay garis bantu) – v2
// v2: garis bantu gigi/wajah (slots.v2.json), mode cermin, galeri.
// v2.1: garis bantu mengikuti rotasi HP (portrait/landscape), juga saat kunci rotasi iOS aktif.
// Tanpa server dan tanpa database: foto + log disimpan di IndexedDB HP ini,
// lalu diekspor sebagai ZIP (photos/*.jpg, log.csv, log.json).

const $ = (id) => document.getElementById(id);
const el = Object.fromEntries([
  'stage', 'video', 'overlay', 'flash', 'whoBtn', 'whoText', 'slotTitle', 'slotCheck',
  'menuBtn', 'level', 'bubble', 'levelText', 'slotChips', 'lastThumb', 'shutter', 'lastInfo',
  'startScreen', 'subject', 'deviceLabel', 'startBtn', 'startNote', 'panel', 'cameraSelect', 'preset',
  'zoomRow', 'zoom', 'zoomOut', 'focusRow', 'focusMode', 'torchRow', 'torch', 'opacity', 'opacityOut',
  'levelBtn', 'summary', 'onlySubject', 'prepZip', 'shareZip', 'zipInfo', 'clearSubject', 'copyDiag',
  'diag', 'toast', 'galleryBtn', 'galleryLabel', 'mirrorBtn', 'gallery', 'galScope', 'galSlot', 'galClose',
  'galBody', 'viewer', 'viewClose', 'viewTitle', 'viewDelete', 'viewStage', 'viewImg', 'viewPrev', 'viewMeta',
  'viewNext',
].map((id) => [id, $(id)]));

const PRESETS = {
  '4032x3024': [4032, 3024],
  '3840x2160': [3840, 2160],
  '1920x1440': [1920, 1440],
  '1920x1080': [1920, 1080],
};
const LEVEL_TOLERANCE = { roll: 2, pitch: 3 }; // derajat, usulan awal
const TARGET_REPS = 3;
const SVG_NS = 'http://www.w3.org/2000/svg';
const PREF_KEY = 'poc-camera-prefs';
const SLOTS_FILE = 'slots.v2.json';
const THUMB_WIDTH = 360;

const state = {
  slotsVersion: '',
  slots: [],
  slot: null,
  stream: null,
  track: null,
  requested: null,
  cameras: [],
  slotOpenedAt: 0,
  metas: [],
  started: false,
  zip: null,
  lastUrl: null,
  level: { on: false, roll: null, pitch: null, g: null },
  // Rotasi fisik HP dari sensor gravitasi. rel = selisih dengan rotasi layar (kunci rotasi aktif).
  turn: { sign: 0, hand: 0, phys: null, rel: 0 },
  mirrorBySlot: {},     // status tombol Cermin per slot (default dari slots.v2.json)
  gallery: { list: [], index: -1, urls: [], viewUrl: null },
};

/* ---------- Penyimpanan lokal (IndexedDB) ---------- */

const db = {
  h: null,
  open() {
    return new Promise((resolve, reject) => {
      const req = indexedDB.open('poc-camera', 2);
      req.onupgradeneeded = () => {
        const d = req.result;
        for (const name of ['meta', 'files', 'thumbs']) {
          if (!d.objectStoreNames.contains(name)) d.createObjectStore(name, { keyPath: 'id' });
        }
      };
      req.onsuccess = () => { this.h = req.result; resolve(); };
      req.onerror = () => reject(req.error);
    });
  },
  addShot(meta, data, thumb) {
    return new Promise((resolve, reject) => {
      const tx = this.h.transaction(['meta', 'files', 'thumbs'], 'readwrite');
      tx.objectStore('meta').put(meta);
      tx.objectStore('files').put({ id: meta.id, data, type: 'image/jpeg' });
      if (thumb) tx.objectStore('thumbs').put({ id: meta.id, data: thumb, type: 'image/jpeg' });
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
      tx.onabort = () => reject(tx.error || new Error('Transaksi dibatalkan (penyimpanan penuh?)'));
    });
  },
  allMeta() {
    return asPromise(this.h.transaction('meta').objectStore('meta').getAll());
  },
  file(id) {
    return asPromise(this.h.transaction('files').objectStore('files').get(id));
  },
  thumb(id) {
    return asPromise(this.h.transaction('thumbs').objectStore('thumbs').get(id));
  },
  putThumb(id, data) {
    return asPromise(this.h.transaction('thumbs', 'readwrite').objectStore('thumbs').put({ id, data, type: 'image/jpeg' }));
  },
  remove(ids) {
    return new Promise((resolve, reject) => {
      const tx = this.h.transaction(['meta', 'files', 'thumbs'], 'readwrite');
      for (const id of ids) {
        tx.objectStore('meta').delete(id);
        tx.objectStore('files').delete(id);
        tx.objectStore('thumbs').delete(id);
      }
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });
  },
};

function asPromise(req) {
  return new Promise((resolve, reject) => {
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

/* ---------- Start ---------- */

async function init() {
  const prefs = loadPrefs();
  if (prefs.subject) el.subject.value = prefs.subject;
  if (prefs.deviceLabel) el.deviceLabel.value = prefs.deviceLabel;
  for (const key of Object.keys(PRESETS)) el.preset.add(new Option(key.replace('x', ' × '), key));
  el.preset.value = PRESETS[prefs.preset] ? prefs.preset : '4032x3024';
  el.opacity.value = prefs.opacity || '0.9';
  applyOpacity();

  if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
    el.startBtn.disabled = true;
    el.startNote.textContent = 'Kamera hanya bisa dipakai dari alamat https://. Buka halaman ini lewat URL deploy, bukan file lokal.';
    return;
  }

  try {
    const res = await fetch(SLOTS_FILE, { cache: 'no-store' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const json = await res.json();
    state.slots = json.slots;
    state.slotsVersion = json.version;
    for (const s of state.slots) state.mirrorBySlot[s.code] = !!s.mirror?.default;
  } catch (e) {
    el.startBtn.disabled = true;
    el.startNote.textContent = `File ${SLOTS_FILE} gagal dimuat (${e.message}). Pastikan file ada di folder yang sama dengan index.html.`;
    return;
  }

  try {
    await db.open();
    state.metas = await db.allMeta();
  } catch (e) {
    el.startNote.textContent = `Penyimpanan lokal tidak tersedia (${e.message}). Mode Private Browsing? Foto tidak akan tersimpan.`;
  }

  selectSlot(state.slots[0].code);
  renderChips();
  updateGalleryButton();
  bind();
}

function bind() {
  el.startBtn.addEventListener('click', onStart);
  el.whoBtn.addEventListener('click', () => {
    el.startBtn.textContent = 'Lanjutkan';
    el.startNote.textContent = '';
    el.startScreen.hidden = false;
  });
  el.menuBtn.addEventListener('click', () => {
    renderSummary();
    updateDiag();
    el.panel.showModal();
  });
  el.shutter.addEventListener('click', capture);
  el.mirrorBtn.addEventListener('click', () => {
    state.mirrorBySlot[state.slot.code] = !state.mirrorBySlot[state.slot.code];
    applyMirrorPreview();
  });
  el.galleryBtn.addEventListener('click', openGallery);
  el.galClose.addEventListener('click', closeGallery);
  el.galScope.addEventListener('change', renderGallery);
  el.galSlot.addEventListener('change', renderGallery);
  el.viewClose.addEventListener('click', closeViewer);
  el.viewPrev.addEventListener('click', () => showInViewer(state.gallery.index - 1));
  el.viewNext.addEventListener('click', () => showInViewer(state.gallery.index + 1));
  el.viewDelete.addEventListener('click', deleteCurrent);
  bindSwipe(el.viewStage);
  el.cameraSelect.addEventListener('change', startCamera);
  el.preset.addEventListener('change', () => { savePrefs(); startCamera(); });
  el.zoom.addEventListener('input', () => { el.zoomOut.value = Number(el.zoom.value).toFixed(2); });
  el.zoom.addEventListener('change', () => applyAdvanced({ zoom: Number(el.zoom.value) }));
  el.focusMode.addEventListener('change', () => applyAdvanced({ focusMode: el.focusMode.value }));
  el.torch.addEventListener('change', () => applyAdvanced({ torch: el.torch.checked }));
  el.opacity.addEventListener('input', () => { applyOpacity(); savePrefs(); });
  el.levelBtn.addEventListener('click', enableLevel);
  el.prepZip.addEventListener('click', prepareZip);
  el.shareZip.addEventListener('click', shareZip);
  el.clearSubject.addEventListener('click', clearSubject);
  el.copyDiag.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(el.diag.textContent); toast('Diagnostik disalin.'); }
    catch { toast('Gagal menyalin. Tekan lama teks diagnostik untuk menyalin manual.'); }
  });
  window.addEventListener('resize', layoutOverlay);
  screen.orientation?.addEventListener?.('change', layoutOverlay);
  el.video.addEventListener('resize', layoutOverlay); // dimensi video berubah saat HP diputar
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden && state.started && (!state.track || state.track.readyState === 'ended')) startCamera();
  });
}

async function onStart() {
  if (!el.subject.value.trim()) {
    el.startNote.textContent = 'Isi ID subjek dulu, mis. R01.';
    el.subject.focus();
    return;
  }
  savePrefs();
  el.whoText.textContent = `${currentSubject()} / ${el.deviceLabel.value}`;
  el.startScreen.hidden = true;
  renderChips();
  if (!state.started) {
    state.started = true;
    // Sensor gerak dipakai untuk waterpass dan rotasi garis bantu; iOS hanya mengizinkan dari ketukan pengguna.
    if (!state.level.on) enableLevel();
    await startCamera();
  }
}

/* ---------- Kamera ---------- */

async function startCamera() {
  stopCamera();
  el.shutter.disabled = true;
  const [w, h] = PRESETS[el.preset.value];
  const deviceId = el.cameraSelect.value;
  const video = {
    width: { ideal: w },
    height: { ideal: h },
    ...(deviceId ? { deviceId: { exact: deviceId } } : { facingMode: { ideal: 'environment' } }),
  };
  state.requested = { preset: el.preset.value, video };

  try {
    state.stream = await navigator.mediaDevices.getUserMedia({ video, audio: false });
  } catch (e) {
    toast(cameraError(e));
    return;
  }

  state.track = state.stream.getVideoTracks()[0];
  state.track.addEventListener('ended', () => toast('Kamera berhenti. Buka Pengaturan lalu pilih kamera lagi, atau muat ulang halaman.'));
  el.video.srcObject = state.stream;
  try { await el.video.play(); } catch { /* autoplay ditangani oleh atribut muted/playsinline */ }
  await waitForVideo();
  await refreshCameras();
  renderControls();
  layoutOverlay();
  el.shutter.disabled = false;
  updateDiag();
}

function stopCamera() {
  state.stream?.getTracks().forEach((t) => t.stop());
  state.stream = null;
  state.track = null;
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
    OverconstrainedError: 'Kamera tidak mendukung pengaturan ini. Pilih resolusi atau kamera lain di Pengaturan.',
    NotReadableError: 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi itu lalu coba lagi.',
  };
  return messages[e.name] || `Kamera gagal dibuka: ${e.name} ${e.message}`;
}

async function refreshCameras() {
  const devices = (await navigator.mediaDevices.enumerateDevices()).filter((d) => d.kind === 'videoinput');
  state.cameras = devices.map((d) => ({ label: d.label, deviceId: d.deviceId }));
  el.cameraSelect.replaceChildren(...devices.map((d, i) => new Option(d.label || `Kamera ${i + 1}`, d.deviceId)));
  const current = state.track?.getSettings().deviceId;
  if (current) el.cameraSelect.value = current;
}

function renderControls() {
  const caps = state.track.getCapabilities?.() || {};
  const s = state.track.getSettings();

  if (caps.zoom) {
    Object.assign(el.zoom, { min: caps.zoom.min, max: caps.zoom.max, step: caps.zoom.step || 0.1 });
    el.zoom.value = s.zoom ?? caps.zoom.min;
    el.zoomOut.value = Number(el.zoom.value).toFixed(2);
    el.zoomRow.hidden = false;
  } else {
    el.zoomRow.hidden = true;
  }

  if (Array.isArray(caps.focusMode) && caps.focusMode.length) {
    el.focusMode.replaceChildren(...caps.focusMode.map((m) => new Option(m, m)));
    if (s.focusMode) el.focusMode.value = s.focusMode;
    el.focusRow.hidden = false;
  } else {
    el.focusRow.hidden = true;
  }

  el.torchRow.hidden = !('torch' in caps);
  el.torch.checked = !!s.torch;
}

async function applyAdvanced(constraint) {
  if (!state.track) return;
  try {
    await state.track.applyConstraints({ advanced: [constraint] });
  } catch (e) {
    toast(`Kamera menolak pengaturan ${Object.keys(constraint)[0]}: ${e.name}`);
  }
  updateDiag();
}

/* ---------- Slot dan overlay ---------- */

function selectSlot(code) {
  state.slot = state.slots.find((s) => s.code === code);
  state.slotOpenedAt = performance.now();
  el.slotTitle.textContent = `${state.slot.code} ${state.slot.label}`;
  el.slotCheck.textContent = state.slot.checklist.join('; ');
  for (const b of el.slotChips.children) b.setAttribute('aria-pressed', String(b.dataset.code === code));
  el.level.hidden = !(state.level.on && state.slot.level);
  applyMirrorPreview();
  layoutOverlay();
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
  const counts = countsFor(currentSubject(), el.deviceLabel.value);
  el.slotChips.replaceChildren(...state.slots.map((s) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.dataset.code = s.code;
    b.setAttribute('aria-pressed', String(state.slot?.code === s.code));
    const n = counts[s.code] || 0;
    b.classList.toggle('done', s.required && n >= TARGET_REPS);
    b.classList.toggle('optional', !s.required);
    b.append(Object.assign(document.createElement('span'), { textContent: s.code }));
    b.append(Object.assign(document.createElement('small'), { textContent: s.required ? `${n}/${TARGET_REPS}` : `${n} foto` }));
    b.addEventListener('click', () => selectSlot(s.code));
    return b;
  }));
}

function layoutOverlay() {
  if (!state.slot) return;
  const vw = el.video.videoWidth;
  const vh = el.video.videoHeight;
  if (!vw || !vh) { el.overlay.replaceChildren(); return; }

  // Overlay ditempatkan tepat di atas area gambar video (object-fit: contain).
  const r = el.stage.getBoundingClientRect();
  const scale = Math.min(r.width / vw, r.height / vh);
  const dw = vw * scale;
  const dh = vh * scale;
  Object.assign(el.overlay.style, {
    left: `${(r.width - dw) / 2}px`,
    top: `${(r.height - dh) / 2}px`,
    width: `${dw}px`,
    height: `${dh}px`,
  });
  el.overlay.setAttribute('viewBox', `0 0 ${dw} ${dh}`);

  // Garis bantu digambar di frame tegak (sesuai posisi HP), lalu diputar bila layar tidak ikut berputar.
  const rel = state.turn.rel;
  const [uw, uh] = rel % 180 ? [dh, dw] : [dw, dh];
  const g = svg('g', { transform: `translate(${dw / 2} ${dh / 2}) rotate(${rel}) translate(${-uw / 2} ${-uh / 2})` });
  g.append(...drawShapes(state.slot.overlay, uw, uh));
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
    group.setAttribute('class', 'guide art');
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

/* ---------- Capture ---------- */

async function capture() {
  const v = el.video;
  if (!state.track || !v.videoWidth) return;
  if (!db.h) { toast('Penyimpanan lokal tidak tersedia; foto tidak bisa disimpan.'); return; }

  el.shutter.disabled = true;
  try {
    const t0 = performance.now();
    const vw = v.videoWidth;
    const vh = v.videoHeight;
    const rel = state.turn.rel;
    const [w, h] = rel % 180 ? [vh, vw] : [vw, vh]; // foto disimpan tegak sesuai posisi HP
    const canvas = document.createElement('canvas');
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
    const captureMs = Math.round(performance.now() - t0);
    const data = await blob.arrayBuffer(); // ArrayBuffer lebih aman di IndexedDB iOS daripada Blob
    const thumb = await (await makeThumb(canvas)).arrayBuffer();

    const subject = currentSubject();
    const device = el.deviceLabel.value;
    const slot = state.slot;
    const rep = nextRep(subject, device, slot.code);
    const now = new Date();
    const settings = state.track.getSettings();

    const meta = {
      id: crypto.randomUUID(),
      timestamp: now.toISOString(),
      subject,
      device_label: device,
      mode: 'A',
      slot: slot.code,
      rep,
      filename: `${safe(subject)}_${safe(device)}_A_${slot.code}_r${rep}_${stamp(now)}.jpg`,
      width: w,
      height: h,
      file_bytes: blob.size,
      camera_label: state.track.label,
      requested_preset: state.requested.preset,
      settings,
      zoom: settings.zoom ?? null,
      torch: settings.torch ?? null,
      focus_mode: settings.focusMode ?? null,
      orientation: w > h ? 'landscape' : 'portrait',
      orientation_ok: slot.orientation === 'any' || (slot.orientation === 'landscape') === (w > h),
      screen_orientation: screen.orientation?.type ?? null,
      rotation_applied: rel,
      level_on: state.level.on,
      level_roll: round1(state.level.roll),
      level_pitch: round1(state.level.pitch),
      level_ok: state.level.on && slot.level ? levelOk() : null,
      mirror_applied: axis,
      since_slot_open_ms: Math.round(performance.now() - state.slotOpenedAt),
      capture_ms: captureMs,
      standalone: isStandalone(),
      imagecapture_available: 'ImageCapture' in window,
      overlay_version: state.slotsVersion,
      user_agent: navigator.userAgent,
    };

    await db.addShot(meta, data, thumb);
    state.metas.push(meta);
    showLast(new Blob([thumb], { type: 'image/jpeg' }), meta);
    flash();
    renderChips();
    state.slotOpenedAt = performance.now();
  } catch (e) {
    toast(`Foto gagal disimpan: ${e.message}`);
  } finally {
    el.shutter.disabled = false;
  }
}

function showLast(blob, meta) {
  if (state.lastUrl) URL.revokeObjectURL(state.lastUrl);
  state.lastUrl = URL.createObjectURL(blob);
  el.lastThumb.src = state.lastUrl;
  el.lastThumb.hidden = false;
  el.galleryLabel.hidden = true;
  el.lastInfo.textContent = `${meta.slot} ke-${meta.rep}\n${meta.width} × ${meta.height}, ${(meta.file_bytes / 1e6).toFixed(1)} MB`;
}

function makeThumb(source) {
  const sw = source.width, sh = source.height;
  const scale = Math.min(1, THUMB_WIDTH / Math.max(sw, sh));
  const c = document.createElement('canvas');
  c.width = Math.round(sw * scale);
  c.height = Math.round(sh * scale);
  c.getContext('2d').drawImage(source, 0, 0, c.width, c.height);
  return new Promise((resolve, reject) =>
    c.toBlob((b) => (b ? resolve(b) : reject(new Error('Gagal membuat thumbnail'))), 'image/jpeg', 0.8));
}

function nextRep(subject, device, code) {
  let max = 0;
  for (const m of state.metas) {
    if (m.subject === subject && m.device_label === device && m.slot === code && m.mode === 'A') max = Math.max(max, m.rep);
  }
  return max + 1;
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
        toast('Izin sensor gerak ditolak. Tutup tab, buka lagi, lalu izinkan saat diminta.');
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
    applyMirrorPreview();
    layoutOverlay();
  }
}

/* ---------- Ekspor dan rekap ---------- */

const CSV_COLUMNS = [
  'timestamp', 'subject', 'device_label', 'mode', 'slot', 'rep', 'filename', 'width', 'height', 'file_bytes',
  'camera_label', 'requested_preset', 'zoom', 'torch', 'focus_mode', 'orientation', 'orientation_ok',
  'level_on', 'level_roll', 'level_pitch', 'level_ok', 'mirror_applied', 'since_slot_open_ms', 'capture_ms',
  'standalone', 'imagecapture_available', 'overlay_version',
];

async function prepareZip() {
  if (!window.JSZip) { toast('Library ZIP belum termuat. Periksa koneksi lalu muat ulang halaman.'); return; }
  if (!db.h) return;
  const subject = currentSubject();
  const metas = (await db.allMeta())
    .filter((m) => !el.onlySubject.checked || m.subject === subject)
    .sort((a, b) => a.timestamp.localeCompare(b.timestamp));
  if (!metas.length) { el.zipInfo.textContent = 'Belum ada foto untuk diekspor.'; return; }

  el.prepZip.disabled = true;
  el.shareZip.hidden = true;
  el.zipInfo.textContent = `Menyiapkan ${metas.length} foto…`;
  try {
    const zip = new window.JSZip();
    for (const m of metas) {
      const f = await db.file(m.id);
      if (f) zip.file(`photos/${m.filename}`, f.data, { binary: true });
    }
    zip.file('log.json', JSON.stringify({ exported_at: new Date().toISOString(), overlay_version: state.slotsVersion, shots: metas }, null, 2));
    zip.file('log.csv', toCSV(metas));
    const blob = await zip.generateAsync({ type: 'blob', compression: 'STORE' }); // JPEG tidak perlu dikompres ulang
    const name = `poc-kamera_${el.onlySubject.checked ? `${safe(subject)}_` : ''}${stamp(new Date())}.zip`;
    state.zip = new File([blob], name, { type: 'application/zip' });
    el.zipInfo.textContent = `${name} siap (${(blob.size / 1e6).toFixed(1)} MB). Ketuk Bagikan ZIP lalu pilih AirDrop atau Simpan ke File.`;
    el.shareZip.hidden = false;
  } catch (e) {
    el.zipInfo.textContent = `ZIP gagal dibuat: ${e.message}. Coba ekspor per subjek.`;
  } finally {
    el.prepZip.disabled = false;
  }
}

function shareZip() {
  const file = state.zip;
  if (!file) return;
  // Dipanggil langsung dari ketukan agar iOS mengizinkan share sheet.
  if (navigator.canShare?.({ files: [file] })) {
    navigator.share({ files: [file], title: file.name }).catch((e) => {
      if (e.name !== 'AbortError') downloadFile(file);
    });
  } else {
    downloadFile(file);
  }
}

function downloadFile(file) {
  const url = URL.createObjectURL(file);
  const a = Object.assign(document.createElement('a'), { href: url, download: file.name });
  document.body.append(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 60_000);
}

function toCSV(rows) {
  const esc = (v) => {
    if (v == null) return '';
    const s = String(v);
    return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
  };
  return [CSV_COLUMNS.join(','), ...rows.map((r) => CSV_COLUMNS.map((c) => esc(r[c])).join(','))].join('\n');
}

async function clearSubject() {
  const subject = currentSubject();
  const ids = state.metas.filter((m) => m.subject === subject).map((m) => m.id);
  if (!ids.length) { toast('Tidak ada foto untuk subjek ini.'); return; }
  if (!confirm(`Hapus ${ids.length} foto subjek ${subject} dari HP ini? Pastikan ZIP sudah diekspor dan tersimpan.`)) return;
  await db.remove(ids);
  state.metas = state.metas.filter((m) => m.subject !== subject);
  state.zip = null;
  el.shareZip.hidden = true;
  renderChips();
  renderSummary();
  updateGalleryButton();
  toast(`${ids.length} foto dihapus.`);
}

function renderSummary() {
  const counts = countsFor(currentSubject(), el.deviceLabel.value);
  const head = '<thead><tr><th>Slot</th><th>Foto</th></tr></thead>';
  const rows = state.slots.map((s) => {
    const n = counts[s.code] || 0;
    const target = s.required ? ` / ${TARGET_REPS}` : ' (tambahan)';
    return `<tr class="${s.required && n >= TARGET_REPS ? 'done' : ''}"><td>${s.code} ${escapeHtml(s.label)}</td><td>${n}${target}</td></tr>`;
  }).join('');
  el.summary.innerHTML = `${head}<tbody>${rows}</tbody>`;
}

function updateDiag() {
  const t = state.track;
  el.diag.textContent = JSON.stringify({
    user_agent: navigator.userAgent,
    standalone: isStandalone(),
    secure_context: window.isSecureContext,
    imagecapture_available: 'ImageCapture' in window,
    requested: state.requested,
    video_frame: { width: el.video.videoWidth, height: el.video.videoHeight },
    track_label: t?.label ?? null,
    settings: t?.getSettings?.() ?? null,
    capabilities: t?.getCapabilities?.() ?? null,
    cameras: state.cameras,
    supported_constraints: navigator.mediaDevices?.getSupportedConstraints?.() ?? null,
    overlay_version: state.slotsVersion,
  }, null, 2);
}

/* ---------- Galeri ---------- */

function galleryList() {
  const subject = currentSubject();
  const device = el.deviceLabel.value;
  const slotFilter = el.galSlot.value;
  const order = Object.fromEntries(state.slots.map((s, i) => [s.code, i]));
  return state.metas
    .filter((m) => el.galScope.value === 'all' || (m.subject === subject && m.device_label === device))
    .filter((m) => !slotFilter || m.slot === slotFilter)
    .sort((a, b) => (order[a.slot] ?? 99) - (order[b.slot] ?? 99) || a.timestamp.localeCompare(b.timestamp));
}

function openGallery() {
  if (!db.h) { toast('Penyimpanan lokal tidak tersedia.'); return; }
  const current = el.galSlot.value;
  el.galSlot.replaceChildren(new Option('Semua slot', ''), ...state.slots.map((s) => new Option(`${s.code} ${s.label}`, s.code)));
  el.galSlot.value = current;
  el.gallery.hidden = false;
  renderGallery();
}

function closeGallery() {
  el.gallery.hidden = true;
  revokeGalleryUrls();
  el.galBody.replaceChildren();
}

function revokeGalleryUrls() {
  for (const u of state.gallery.urls) URL.revokeObjectURL(u);
  state.gallery.urls = [];
}

async function renderGallery() {
  revokeGalleryUrls();
  const list = galleryList();
  state.gallery.list = list;
  if (!list.length) {
    const empty = document.createElement('p');
    empty.className = 'galEmpty';
    empty.textContent = 'Belum ada foto untuk pilihan ini. Ambil foto dari layar kamera, lalu buka galeri lagi.';
    el.galBody.replaceChildren(empty);
    return;
  }
  // Kelompokkan per slot, sesuai urutan template.
  const groups = new Map();
  list.forEach((m, i) => {
    if (!groups.has(m.slot)) groups.set(m.slot, []);
    groups.get(m.slot).push([m, i]);
  });
  const frag = document.createDocumentFragment();
  const pending = [];
  for (const [code, items] of groups) {
    const slot = state.slots.find((s) => s.code === code);
    const h = document.createElement('h3');
    h.textContent = `${code} ${slot ? slot.label : ''} (${items.length})`;
    const grid = document.createElement('div');
    grid.className = 'galGrid';
    for (const [m, i] of items) {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'tile';
      b.setAttribute('aria-label', `${m.slot} ke-${m.rep}, ${m.subject}`);
      const img = document.createElement('img');
      img.alt = '';
      const cap = document.createElement('span');
      cap.textContent = el.galScope.value === 'all' ? `${m.subject} ke-${m.rep}` : `ke-${m.rep}`;
      b.append(img, cap);
      b.addEventListener('click', () => showInViewer(i));
      grid.append(b);
      pending.push([m, img]);
    }
    frag.append(h, grid);
  }
  el.galBody.replaceChildren(frag);
  // Thumbnail dimuat berurutan agar memori HP tidak melonjak.
  for (const [m, img] of pending) {
    if (el.gallery.hidden) return;
    try {
      const url = await thumbUrl(m.id);
      if (url) img.src = url;
    } catch { /* biarkan tile kosong */ }
  }
}

async function thumbUrl(id) {
  let t = await db.thumb(id);
  if (!t) {
    // Foto dari versi pertama belum punya thumbnail: buat sekarang lalu simpan.
    const f = await db.file(id);
    if (!f) return null;
    const bmp = await createImageBitmap(new Blob([f.data], { type: f.type }));
    const data = await (await makeThumb(bmp)).arrayBuffer();
    bmp.close?.();
    await db.putThumb(id, data);
    t = { data };
  }
  const url = URL.createObjectURL(new Blob([t.data], { type: 'image/jpeg' }));
  state.gallery.urls.push(url);
  return url;
}

async function showInViewer(index) {
  const list = state.gallery.list;
  if (index < 0 || index >= list.length) return;
  state.gallery.index = index;
  const m = list[index];
  const f = await db.file(m.id);
  if (!f) { toast('File foto tidak ditemukan.'); return; }
  if (state.gallery.viewUrl) URL.revokeObjectURL(state.gallery.viewUrl);
  state.gallery.viewUrl = URL.createObjectURL(new Blob([f.data], { type: f.type }));
  el.viewImg.src = state.gallery.viewUrl;
  el.viewTitle.textContent = `${m.slot} ke-${m.rep} (${index + 1}/${list.length})`;
  const when = new Date(m.timestamp).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
  el.viewMeta.textContent = [
    `${m.subject}, ${m.device_label}`,
    `${m.width} × ${m.height}, ${(m.file_bytes / 1e6).toFixed(1)} MB`,
    m.camera_label || '',
    m.mirror_applied ? `Cermin: dibalik ${m.mirror_applied === 'vertical' ? 'vertikal' : 'horizontal'}` : '',
    m.level_ok == null ? '' : (m.level_ok ? 'Waterpass: rata' : `Waterpass: miring ${Math.abs(m.level_roll)}°`),
    when,
  ].filter(Boolean).join('\n');
  el.viewPrev.disabled = index === 0;
  el.viewNext.disabled = index === list.length - 1;
  el.viewer.hidden = false;
}

function closeViewer() {
  el.viewer.hidden = true;
  if (state.gallery.viewUrl) URL.revokeObjectURL(state.gallery.viewUrl);
  state.gallery.viewUrl = null;
  el.viewImg.removeAttribute('src');
}

async function deleteCurrent() {
  const m = state.gallery.list[state.gallery.index];
  if (!m || !confirm(`Hapus foto ${m.slot} ke-${m.rep} dari HP ini?`)) return;
  await db.remove([m.id]);
  state.metas = state.metas.filter((x) => x.id !== m.id);
  closeViewer();
  renderChips();
  updateGalleryButton();
  renderGallery();
  toast('Foto dihapus.');
}

function bindSwipe(target) {
  let x0 = null;
  target.addEventListener('touchstart', (e) => { x0 = e.touches.length === 1 ? e.touches[0].clientX : null; }, { passive: true });
  target.addEventListener('touchend', (e) => {
    if (x0 == null) return;
    const dx = e.changedTouches[0].clientX - x0;
    x0 = null;
    if (Math.abs(dx) < 60) return;
    showInViewer(state.gallery.index + (dx < 0 ? 1 : -1));
  });
}

async function updateGalleryButton() {
  // Tampilkan thumbnail foto terakhir di tombol galeri (bila ada).
  const last = [...state.metas].sort((a, b) => b.timestamp.localeCompare(a.timestamp))[0];
  if (!last || !db.h) {
    el.lastThumb.hidden = true;
    el.galleryLabel.hidden = false;
    return;
  }
  try {
    const t = await db.thumb(last.id);
    if (!t) return;
    showLast(new Blob([t.data], { type: 'image/jpeg' }), last);
  } catch { /* abaikan */ }
}

/* ---------- Utilitas ---------- */

function countsFor(subject, device) {
  const c = {};
  for (const m of state.metas) {
    if (m.subject === subject && m.device_label === device && m.mode === 'A') c[m.slot] = (c[m.slot] || 0) + 1;
  }
  return c;
}

function currentSubject() { return el.subject.value.trim().toUpperCase(); }
function isStandalone() { return window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true; }
function safe(s) { return String(s).replace(/[^a-z0-9-]+/gi, '-'); }
function stamp(d) { return d.toISOString().replace(/[-:]/g, '').replace(/\.\d+Z$/, 'Z'); }
function round1(n) { return n == null ? null : Math.round(n * 10) / 10; }
function deg(rad) { return (rad * 180) / Math.PI; }
function escapeHtml(s) { return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

function loadPrefs() {
  try { return JSON.parse(localStorage.getItem(PREF_KEY)) || {}; } catch { return {}; }
}
function savePrefs() {
  try {
    localStorage.setItem(PREF_KEY, JSON.stringify({
      subject: el.subject.value.trim(),
      deviceLabel: el.deviceLabel.value,
      preset: el.preset.value,
      opacity: el.opacity.value,
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
