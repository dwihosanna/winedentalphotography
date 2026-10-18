// Edit non-destruktif (FR-13, FR-14). Pratinjau di canvas; server merender ulang dari file asli dengan
// urutan yang sama (app/Support/PhotoEdit.php): putar 90° → balik → luruskan + potong sudut kosong → crop.
// Crop seperti app Foto iPhone: bingkai tetap di tengah, foto di bawahnya dicubit (zoom) dan digeser; sudut/sisi
// bingkai ditarik untuk memperkecil, lalu bingkai kembali memenuhi layar (foto ikut membesar). Crop tersimpan bisa
// di-crop lagi karena selalu dihitung dari foto asli.
const config = JSON.parse(document.getElementById('editor-config').textContent);
const root = document.querySelector('[data-editor]');
const canvas = root.querySelector('[data-canvas]');
const stage = root.querySelector('[data-stage]');
const straightenInput = root.querySelector('[data-straighten]');
const saveBtn = root.querySelector('[data-save]');

// Pratinjau memakai piksel asli (dibatasi ±16 MP, batas kanvas Safari iOS) agar tetap tajam saat di-zoom.
// Saat slider luruskan digeser dipakai salinan kecil dulu, versi penuh menyusul setelah slider diam.
const MAX_PIXELS = 16_000_000;
const LO_MAX = 1600;
const HANDLE = 26; // jarak sentuh sudut/sisi bingkai (px CSS)
const PAD = 22; // jarak bingkai dari tepi kanvas
const MIN_CROP = 0.05; // sama dengan validasi server
const FULL = { x: 0, y: 0, w: 1, h: 1 };

const p = { rotate: 0, flip_h: false, flip_v: false, straighten: 0, crop: null, auto_flip: false, ...config.params };
let crop = { ...(p.crop ?? FULL) };
let locked = !p.crop || Math.abs(p.crop.w - p.crop.h) < 0.002;
const initial = serialize();

const srcHi = document.createElement('canvas');
const srcLo = document.createElement('canvas');
const baseHi = document.createElement('canvas');
const baseLo = document.createElement('canvas');
let hiFresh = false;
let hiTimer = 0;
const geo = { W: 0, H: 0 }; // ukuran gambar (satuan piksel pratinjau penuh) setelah putar + luruskan

let frame = null; // bingkai di layar saat ditarik/beranimasi; null = pas di tengah (fit)
let gesture = null;
let anim = 0;
const pointers = new Map();

load();

async function load() {
  const img = new Image();
  img.src = config.src;
  try {
    await img.decode();
  } catch {
    root.querySelector('[data-loading]').textContent = 'Foto gagal dimuat. Muat ulang halaman.';
    return;
  }
  // Browser sudah menerapkan orientasi EXIF pada naturalWidth/Height dan drawImage.
  const nw = img.naturalWidth;
  const nh = img.naturalHeight;
  scaleInto(srcHi, img, Math.min(1, Math.sqrt(MAX_PIXELS / (nw * nh))));
  scaleInto(srcLo, img, Math.min(1, LO_MAX / Math.max(nw, nh)));
  root.querySelector('[data-loading]').remove();

  straightenInput.value = p.straighten;
  bind();
  renderBase(true);
  new ResizeObserver(() => draw()).observe(stage);
}

function scaleInto(target, img, s) {
  target.width = Math.max(1, Math.round(img.naturalWidth * s));
  target.height = Math.max(1, Math.round(img.naturalHeight * s));
  const ctx = target.getContext('2d');
  ctx.imageSmoothingQuality = 'high';
  ctx.drawImage(img, 0, 0, target.width, target.height);
}

function inscribed(w, h, deg) {
  const a = (Math.abs(deg) * Math.PI) / 180;
  const k = Math.min(w / (w * Math.cos(a) + h * Math.sin(a)), h / (w * Math.sin(a) + h * Math.cos(a)));
  return [w * k, h * k];
}

function renderInto(base, src) {
  const quarter = p.rotate % 180 !== 0;
  const [w1, h1] = quarter ? [src.height, src.width] : [src.width, src.height];
  const [bw, bh] = inscribed(w1, h1, p.straighten);
  base.width = Math.max(1, Math.round(bw));
  base.height = Math.max(1, Math.round(bh));
  const ctx = base.getContext('2d');
  ctx.imageSmoothingQuality = 'high';
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, base.width, base.height);
  ctx.translate(base.width / 2, base.height / 2);
  ctx.rotate((p.straighten * Math.PI) / 180);
  ctx.scale(p.flip_h ? -1 : 1, p.flip_v ? -1 : 1);
  ctx.rotate((p.rotate * Math.PI) / 180);
  ctx.drawImage(src, -src.width / 2, -src.height / 2);
}

/** full = langsung render resolusi penuh; false = salinan kecil sekarang, penuh setelah 250 ms tanpa perubahan. */
function renderBase(full) {
  const quarter = p.rotate % 180 !== 0;
  const [w1, h1] = quarter ? [srcHi.height, srcHi.width] : [srcHi.width, srcHi.height];
  [geo.W, geo.H] = inscribed(w1, h1, p.straighten);
  clearTimeout(hiTimer);
  if (full) {
    renderInto(baseHi, srcHi);
    hiFresh = true;
  } else {
    renderInto(baseLo, srcLo);
    hiFresh = false;
    hiTimer = setTimeout(() => renderBase(true), 250);
  }
  draw();
}

/* ---------- Geometri layar ---------- */

function stageSize() {
  return { sw: stage.clientWidth, sh: Math.round(Math.max(280, window.innerHeight * 0.6)) };
}

/** Bingkai crop terbesar yang muat di kanvas, rasio = rasio crop. */
function fit(c) {
  const { sw, sh } = stageSize();
  const a = (c.w * geo.W) / (c.h * geo.H);
  const fw = Math.min(sw - 2 * PAD, (sh - 2 * PAD) * a);
  const fh = fw / a;
  return { x: (sw - fw) / 2, y: (sh - fh) / 2, w: fw, h: fh };
}

/** Bingkai, skala (px CSS per piksel gambar) dan posisi pojok kiri atas gambar di layar. */
function view() {
  const f = frame ?? fit(crop);
  const k = f.w / (crop.w * geo.W);
  return { f, k, ox: f.x - crop.x * geo.W * k, oy: f.y - crop.y * geo.H * k };
}

/* ---------- Gambar ---------- */

function draw() {
  if (!geo.W) return;
  const { sw, sh } = stageSize();
  const dpr = window.devicePixelRatio || 1;
  canvas.style.width = `${sw}px`;
  canvas.style.height = `${sh}px`;
  if (canvas.width !== Math.round(sw * dpr)) canvas.width = Math.round(sw * dpr);
  if (canvas.height !== Math.round(sh * dpr)) canvas.height = Math.round(sh * dpr);

  const { f, k, ox, oy } = view();
  const ctx = canvas.getContext('2d');
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  ctx.fillStyle = '#111';
  ctx.fillRect(0, 0, sw, sh);
  ctx.imageSmoothingQuality = 'high';
  ctx.drawImage(hiFresh ? baseHi : baseLo, ox, oy, geo.W * k, geo.H * k);

  ctx.fillStyle = 'rgb(0 0 0 / .6)';
  ctx.beginPath();
  ctx.rect(0, 0, sw, sh);
  ctx.rect(f.x, f.y, f.w, f.h);
  ctx.fill('evenodd');

  // Garis sepertiga membantu meluruskan bidang oklusal / garis interpupil.
  ctx.strokeStyle = 'rgb(255 255 255 / .45)';
  ctx.lineWidth = 1;
  ctx.beginPath();
  for (const t of [1 / 3, 2 / 3]) {
    ctx.moveTo(f.x + f.w * t, f.y);
    ctx.lineTo(f.x + f.w * t, f.y + f.h);
    ctx.moveTo(f.x, f.y + f.h * t);
    ctx.lineTo(f.x + f.w, f.y + f.h * t);
  }
  ctx.stroke();
  ctx.strokeStyle = '#fff';
  ctx.lineWidth = 1.5;
  ctx.strokeRect(f.x, f.y, f.w, f.h);

  // Sudut berbentuk L (seperti app Foto); garis pendek di tengah sisi bila crop bebas.
  const L = Math.min(22, f.w / 3, f.h / 3);
  ctx.lineWidth = 4;
  ctx.beginPath();
  for (const [cx, cy, dx, dy] of [[f.x, f.y, 1, 1], [f.x + f.w, f.y, -1, 1], [f.x, f.y + f.h, 1, -1], [f.x + f.w, f.y + f.h, -1, -1]]) {
    ctx.moveTo(cx - 2 * dx, cy + L * dy);
    ctx.lineTo(cx - 2 * dx, cy - 2 * dy);
    ctx.lineTo(cx + L * dx, cy - 2 * dy);
  }
  if (!locked) {
    const mx = f.x + f.w / 2;
    const my = f.y + f.h / 2;
    ctx.moveTo(mx - L / 2, f.y - 2); ctx.lineTo(mx + L / 2, f.y - 2);
    ctx.moveTo(mx - L / 2, f.y + f.h + 2); ctx.lineTo(mx + L / 2, f.y + f.h + 2);
    ctx.moveTo(f.x - 2, my - L / 2); ctx.lineTo(f.x - 2, my + L / 2);
    ctx.moveTo(f.x + f.w + 2, my - L / 2); ctx.lineTo(f.x + f.w + 2, my + L / 2);
  }
  ctx.stroke();

  refreshControls();
}

/* ---------- Gestur ---------- */

function bind() {
  root.addEventListener('click', (e) => {
    const action = e.target.closest('[data-action]')?.dataset.action;
    const aspect = e.target.closest('[data-aspect]')?.dataset.aspect;
    if (action === 'rotate-left' || action === 'rotate-right') {
      p.rotate = (p.rotate + (action === 'rotate-right' ? 90 : 270)) % 360;
      crop = { ...FULL }; // koordinat crop relatif berubah arti setelah diputar
      settleNow();
      renderBase(true);
    } else if (action === 'flip-h') {
      p.flip_h = !p.flip_h;
      p.auto_flip = false;
      crop.x = 1 - crop.x - crop.w;
      settleNow();
      renderBase(true);
    } else if (action === 'flip-v') {
      p.flip_v = !p.flip_v;
      p.auto_flip = false;
      crop.y = 1 - crop.y - crop.h;
      settleNow();
      renderBase(true);
    } else if (action === 'crop-reset') {
      crop = { ...FULL };
      settle();
    } else if (aspect) {
      locked = aspect === 'locked';
      if (locked) {
        // Rasio asli = w sama dengan h dalam koordinat relatif.
        const s = Math.min(crop.w, crop.h);
        crop = { x: crop.x + (crop.w - s) / 2, y: crop.y + (crop.h - s) / 2, w: s, h: s };
      }
      settle();
    }
  });

  straightenInput.addEventListener('input', () => {
    p.straighten = Number(straightenInput.value);
    renderBase(false);
  });

  // Safari: cubit di kanvas jangan sampai memperbesar halaman.
  for (const type of ['gesturestart', 'gesturechange']) canvas.addEventListener(type, (e) => e.preventDefault());

  canvas.addEventListener('pointerdown', (e) => {
    canvas.setPointerCapture(e.pointerId);
    pointers.set(e.pointerId, point(e));
    e.preventDefault();
    settleNow();
    if (pointers.size === 2) startPinch();
    else if (pointers.size === 1) startSingle(point(e));
  });

  canvas.addEventListener('pointermove', (e) => {
    if (!pointers.has(e.pointerId)) return;
    pointers.set(e.pointerId, point(e));
    if (!gesture) return;
    if (gesture.mode === 'pinch') movePinch();
    else if (gesture.mode === 'pan') movePan(point(e));
    else moveResize(point(e));
    draw();
  });

  const end = (e) => {
    if (!pointers.delete(e.pointerId)) return;
    if (gesture?.mode === 'resize') settle();
    gesture = null;
    // Satu jari tersisa setelah cubit: lanjut geser dari posisi jari itu.
    if (pointers.size === 1) startPan([...pointers.values()][0]);
  };
  canvas.addEventListener('pointerup', end);
  canvas.addEventListener('pointercancel', end);

  // Desktop: roda mouse / trackpad = zoom di posisi kursor.
  canvas.addEventListener('wheel', (e) => {
    e.preventDefault();
    settleNow();
    const pt = point(e);
    const v = view();
    zoomTo(v, pt, pt, v.k * Math.exp(-e.deltaY * 0.0015));
    draw();
  }, { passive: false });

  root.querySelector('[data-save-form]').addEventListener('submit', () => {
    root.querySelector('[data-params]').value = serialize();
    saveBtn.disabled = true;
    saveBtn.textContent = 'Menyimpan…';
  });
}

function point(e) {
  const rect = canvas.getBoundingClientRect();
  return { x: e.clientX - rect.left, y: e.clientY - rect.top };
}

function startSingle(pt) {
  const v = view();
  const { f } = v;
  const near = (a, b) => Math.abs(a - b) <= HANDLE;
  const inX = pt.x > f.x - HANDLE && pt.x < f.x + f.w + HANDLE;
  const inY = pt.y > f.y - HANDLE && pt.y < f.y + f.h + HANDLE;
  const dirX = inY && near(pt.x, f.x) ? -1 : inY && near(pt.x, f.x + f.w) ? 1 : 0;
  const dirY = inX && near(pt.y, f.y) ? -1 : inX && near(pt.y, f.y + f.h) ? 1 : 0;
  // Rasio terkunci: hanya sudut. Bebas: sudut dan sisi.
  if ((dirX && dirY) || (!locked && (dirX || dirY))) {
    gesture = { mode: 'resize', dirX, dirY, ...v };
  } else {
    startPan(pt);
  }
}

function startPan(pt) {
  gesture = { mode: 'pan', start: pt, c0: { ...crop }, k: view().k };
}

/** Geser foto di bawah bingkai: foto mengikuti jari, jadi area crop bergerak berlawanan. */
function movePan(pt) {
  const { start, c0, k } = gesture;
  crop.x = clamp(c0.x - (pt.x - start.x) / (k * geo.W), 0, 1 - crop.w);
  crop.y = clamp(c0.y - (pt.y - start.y) / (k * geo.H), 0, 1 - crop.h);
}

function startPinch() {
  const [a, b] = [...pointers.values()];
  gesture = { mode: 'pinch', mid0: mid(a, b), d0: Math.hypot(a.x - b.x, a.y - b.y) || 1, v0: view() };
}

function movePinch() {
  const [a, b] = [...pointers.values()];
  const { mid0, d0, v0 } = gesture;
  zoomTo(v0, mid0, mid(a, b), (v0.k * Math.hypot(a.x - b.x, a.y - b.y)) / d0);
}

/**
 * Zoom dengan bingkai tetap: titik foto di bawah `from` (pada tampilan v) pindah ke `to` dengan skala k.
 * Zoom masuk = crop mengecil; zoom keluar dibatasi sampai crop seluas foto.
 */
function zoomTo(v, from, to, k) {
  const { f } = v;
  const kMin = Math.max(f.w / geo.W, f.h / geo.H);
  const kMax = Math.min(f.w / (MIN_CROP * geo.W), f.h / (MIN_CROP * geo.H));
  k = clamp(k, kMin, Math.max(kMin, kMax));
  const u = (from.x - v.ox) / v.k;
  const w = (from.y - v.oy) / v.k;
  const ox = to.x - u * k;
  const oy = to.y - w * k;
  const cw = Math.min(1, f.w / (k * geo.W));
  const ch = Math.min(1, f.h / (k * geo.H));
  crop = {
    x: clamp((f.x - ox) / (k * geo.W), 0, 1 - cw),
    y: clamp((f.y - oy) / (k * geo.H), 0, 1 - ch),
    w: cw,
    h: ch,
  };
}

/** Tarik sudut/sisi: foto diam, bingkai mengecil/membesar (dibatasi tepi foto dan kanvas). */
function moveResize(pt) {
  const { dirX, dirY, f, k, ox, oy } = gesture;
  const { sw, sh } = stageSize();
  const left = Math.max(ox, 0);
  const right = Math.min(ox + geo.W * k, sw);
  const top = Math.max(oy, 0);
  const bottom = Math.min(oy + geo.H * k, sh);
  const minW = Math.max(40, MIN_CROP * geo.W * k);
  const minH = Math.max(40, MIN_CROP * geo.H * k);
  const ax = dirX > 0 ? f.x : f.x + f.w; // sisi berlawanan = jangkar
  const ay = dirY > 0 ? f.y : f.y + f.h;
  const availW = dirX > 0 ? right - ax : ax - left;
  const availH = dirY > 0 ? bottom - ay : ay - top;

  let w = dirX ? clamp((pt.x - ax) * dirX, minW, availW) : f.w;
  let h = dirY ? clamp((pt.y - ay) * dirY, minH, availH) : f.h;
  if (locked) {
    const r = geo.W / geo.H;
    w = Math.max(minW, Math.min(Math.max(w, h * r), availW, availH * r));
    h = w / r;
  }
  frame = {
    x: dirX > 0 ? ax : dirX < 0 ? ax - w : f.x,
    y: dirY > 0 ? ay : dirY < 0 ? ay - h : f.y,
    w,
    h,
  };
  const cw = Math.min(1, w / (k * geo.W));
  const ch = Math.min(1, h / (k * geo.H));
  crop = {
    x: clamp((frame.x - ox) / (k * geo.W), 0, 1 - cw),
    y: clamp((frame.y - oy) / (k * geo.H), 0, 1 - ch),
    w: cw,
    h: ch,
  };
}

/** Bingkai kembali memenuhi kanvas dengan animasi singkat; foto ikut membesar, area crop tetap. */
function settle() {
  cancelAnimationFrame(anim);
  const from = frame ?? fit(crop);
  const to = fit(crop);
  const t0 = performance.now();
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const step = (now) => {
    const t = reduce ? 1 : Math.min(1, (now - t0) / 220);
    const e = 1 - (1 - t) ** 3;
    frame = t < 1 ? { x: lerp(from.x, to.x, e), y: lerp(from.y, to.y, e), w: lerp(from.w, to.w, e), h: lerp(from.h, to.h, e) } : null;
    draw();
    if (t < 1) anim = requestAnimationFrame(step);
  };
  anim = requestAnimationFrame(step);
}

function settleNow() {
  cancelAnimationFrame(anim);
  frame = null;
}

const mid = (a, b) => ({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 });
const lerp = (a, b, t) => a + (b - a) * t;

function clamp(v, min, max) {
  return Math.min(max, Math.max(min, v));
}

function serialize() {
  const full = crop.w > 0.999 && crop.h > 0.999;
  const round = (v) => Math.round(v * 1e5) / 1e5;
  return JSON.stringify({
    rotate: p.rotate,
    flip_h: p.flip_h,
    flip_v: p.flip_v,
    straighten: p.straighten,
    crop: full ? null : { x: round(crop.x), y: round(crop.y), w: round(crop.w), h: round(crop.h) },
    auto_flip: p.auto_flip,
  });
}

function refreshControls() {
  root.querySelector('[data-action="flip-h"]').setAttribute('aria-pressed', String(p.flip_h));
  root.querySelector('[data-action="flip-v"]').setAttribute('aria-pressed', String(p.flip_v));
  for (const b of root.querySelectorAll('[data-aspect]')) b.setAttribute('aria-pressed', String((b.dataset.aspect === 'locked') === locked));
  root.querySelector('[data-straighten-value]').textContent = `${p.straighten.toFixed(1).replace('.', ',')}°`;
  root.querySelector('[data-auto-flip-note]').hidden = !p.auto_flip;
  saveBtn.disabled = serialize() === initial;
}
