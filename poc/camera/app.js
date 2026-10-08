// PoC kamera ortodonti – Mode A (getUserMedia + overlay garis bantu)
// Tanpa server dan tanpa database: foto + log disimpan di IndexedDB HP ini,
// lalu diekspor sebagai ZIP (photos/*.jpg, log.csv, log.json).

const $ = (id) => document.getElementById(id);
const el = Object.fromEntries([
  'stage', 'video', 'overlay', 'rotateHint', 'flash', 'whoBtn', 'whoText', 'slotTitle', 'slotCheck',
  'menuBtn', 'level', 'bubble', 'levelText', 'slotChips', 'lastThumb', 'shutter', 'lastInfo',
  'startScreen', 'subject', 'deviceLabel', 'startBtn', 'startNote', 'panel', 'cameraSelect', 'preset',
  'zoomRow', 'zoom', 'zoomOut', 'focusRow', 'focusMode', 'torchRow', 'torch', 'opacity', 'opacityOut',
  'levelBtn', 'summary', 'onlySubject', 'prepZip', 'shareZip', 'zipInfo', 'clearSubject', 'copyDiag',
  'diag', 'toast',
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
};

/* ---------- Penyimpanan lokal (IndexedDB) ---------- */

const db = {
  h: null,
  open() {
    return new Promise((resolve, reject) => {
      const req = indexedDB.open('poc-camera', 1);
      req.onupgradeneeded = () => {
        req.result.createObjectStore('meta', { keyPath: 'id' });
        req.result.createObjectStore('files', { keyPath: 'id' });
      };
      req.onsuccess = () => { this.h = req.result; resolve(); };
      req.onerror = () => reject(req.error);
    });
  },
  addShot(meta, data) {
    return new Promise((resolve, reject) => {
      const tx = this.h.transaction(['meta', 'files'], 'readwrite');
      tx.objectStore('meta').put(meta);
      tx.objectStore('files').put({ id: meta.id, data, type: 'image/jpeg' });
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
  remove(ids) {
    return new Promise((resolve, reject) => {
      const tx = this.h.transaction(['meta', 'files'], 'readwrite');
      for (const id of ids) {
        tx.objectStore('meta').delete(id);
        tx.objectStore('files').delete(id);
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
    const res = await fetch('slots.v1.json', { cache: 'no-store' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const json = await res.json();
    state.slots = json.slots;
    state.slotsVersion = json.version;
  } catch (e) {
    el.startBtn.disabled = true;
    el.startNote.textContent = `File slots.v1.json gagal dimuat (${e.message}). Pastikan file ada di folder yang sama dengan index.html.`;
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
  layoutOverlay();
}

function renderChips() {
  const counts = countsFor(currentSubject(), el.deviceLabel.value);
  el.slotChips.replaceChildren(...state.slots.map((s) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.dataset.code = s.code;
    b.setAttribute('aria-pressed', String(state.slot?.code === s.code));
    const n = counts[s.code] || 0;
    b.classList.toggle('done', n >= TARGET_REPS);
    b.append(Object.assign(document.createElement('span'), { textContent: s.code }));
    b.append(Object.assign(document.createElement('small'), { textContent: `${n}/${TARGET_REPS}` }));
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
  el.overlay.replaceChildren(...drawShapes(state.slot.overlay, dw, dh));

  const wantLandscape = state.slot.orientation === 'landscape';
  el.rotateHint.textContent = `Putar HP ke posisi ${wantLandscape ? 'landscape' : 'portrait'} untuk slot ini.`;
  el.rotateHint.hidden = wantLandscape === (vw > vh);
}

function drawShapes(shapes, w, h) {
  const out = [];
  for (const sh of shapes) {
    let node;
    if (sh.t === 'line') node = svg('line', { x1: sh.x1 * w, y1: sh.y1 * h, x2: sh.x2 * w, y2: sh.y2 * h });
    else if (sh.t === 'ellipse') node = svg('ellipse', { cx: sh.cx * w, cy: sh.cy * h, rx: sh.rx * w, ry: sh.ry * h });
    else if (sh.t === 'rect') node = svg('rect', { x: sh.x * w, y: sh.y * h, width: sh.w * w, height: sh.h * h, rx: 8 });
    else if (sh.t === 'polyline') node = svg('polyline', { points: sh.points.map(([x, y]) => `${x * w},${y * h}`).join(' ') });
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
    const w = v.videoWidth;
    const h = v.videoHeight;
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    canvas.getContext('2d').drawImage(v, 0, 0, w, h);
    const blob = await new Promise((resolve, reject) =>
      canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('Gagal membuat JPEG'))), 'image/jpeg', 0.95));
    const captureMs = Math.round(performance.now() - t0);
    const data = await blob.arrayBuffer(); // ArrayBuffer lebih aman di IndexedDB iOS daripada Blob

    const subject = currentSubject();
    const device = el.deviceLabel.value;
    const slot = state.slot;
    const rep = (countsFor(subject, device)[slot.code] || 0) + 1;
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
      orientation: screen.orientation?.type || (w > h ? 'landscape' : 'portrait'),
      orientation_ok: (slot.orientation === 'landscape') === (w > h),
      level_on: state.level.on,
      level_roll: round1(state.level.roll),
      level_pitch: round1(state.level.pitch),
      level_ok: state.level.on && slot.level ? levelOk() : null,
      mirror_required: slot.mirror,
      since_slot_open_ms: Math.round(performance.now() - state.slotOpenedAt),
      capture_ms: captureMs,
      standalone: isStandalone(),
      imagecapture_available: 'ImageCapture' in window,
      overlay_version: state.slotsVersion,
      user_agent: navigator.userAgent,
    };

    await db.addShot(meta, data);
    state.metas.push(meta);
    showLast(blob, meta);
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
  el.lastInfo.textContent = `${meta.slot} ke-${meta.rep}\n${meta.width} × ${meta.height}, ${(meta.file_bytes / 1e6).toFixed(1)} MB`;
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
  const landscape = Math.abs(screenAngle()) === 90;
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

/* ---------- Ekspor dan rekap ---------- */

const CSV_COLUMNS = [
  'timestamp', 'subject', 'device_label', 'mode', 'slot', 'rep', 'filename', 'width', 'height', 'file_bytes',
  'camera_label', 'requested_preset', 'zoom', 'torch', 'focus_mode', 'orientation', 'orientation_ok',
  'level_on', 'level_roll', 'level_pitch', 'level_ok', 'mirror_required', 'since_slot_open_ms', 'capture_ms',
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
  toast(`${ids.length} foto dihapus.`);
}

function renderSummary() {
  const counts = countsFor(currentSubject(), el.deviceLabel.value);
  const head = '<thead><tr><th>Slot</th><th>Foto</th></tr></thead>';
  const rows = state.slots.map((s) => {
    const n = counts[s.code] || 0;
    return `<tr class="${n >= TARGET_REPS ? 'done' : ''}"><td>${s.code} ${escapeHtml(s.label)}</td><td>${n} / ${TARGET_REPS}</td></tr>`;
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
