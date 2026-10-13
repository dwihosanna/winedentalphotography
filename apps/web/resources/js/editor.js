// Edit non-destruktif (FR-13, FR-14). Pratinjau di canvas; server merender ulang dari file asli dengan
// urutan yang sama (app/Support/PhotoEdit.php): putar 90° → balik → luruskan + potong sudut kosong → crop.
const config = JSON.parse(document.getElementById('editor-config').textContent);
const root = document.querySelector('[data-editor]');
const canvas = root.querySelector('[data-canvas]');
const stage = root.querySelector('[data-stage]');
const straightenInput = root.querySelector('[data-straighten]');
const saveBtn = root.querySelector('[data-save]');

const PREVIEW_MAX = 1600; // sisi terpanjang pratinjau; hasil akhir tetap resolusi penuh di server
const HANDLE = 22; // jarak sentuh sudut crop (px CSS)
const MIN_CROP = 0.05;
const FULL = { x: 0, y: 0, w: 1, h: 1 };

const p = { rotate: 0, flip_h: false, flip_v: false, straighten: 0, crop: null, auto_flip: false, ...config.params };
let crop = { ...(p.crop ?? FULL) };
let locked = !p.crop || Math.abs(p.crop.w - p.crop.h) < 0.002;
const initial = serialize();

const src = document.createElement('canvas');
const base = document.createElement('canvas');
let view = { scale: 1, w: 0, h: 0 };
let drag = null;

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
  const s = Math.min(1, PREVIEW_MAX / Math.max(img.naturalWidth, img.naturalHeight));
  src.width = Math.round(img.naturalWidth * s);
  src.height = Math.round(img.naturalHeight * s);
  src.getContext('2d').drawImage(img, 0, 0, src.width, src.height);
  root.querySelector('[data-loading]').remove();

  straightenInput.value = p.straighten;
  bind();
  renderBase();
  new ResizeObserver(draw).observe(stage);
}

function inscribed(w, h, deg) {
  const a = (Math.abs(deg) * Math.PI) / 180;
  const k = Math.min(w / (w * Math.cos(a) + h * Math.sin(a)), h / (w * Math.sin(a) + h * Math.cos(a)));
  return [w * k, h * k];
}

function renderBase() {
  const quarter = p.rotate % 180 !== 0;
  const [w1, h1] = quarter ? [src.height, src.width] : [src.width, src.height];
  const [bw, bh] = inscribed(w1, h1, p.straighten);
  base.width = Math.max(1, Math.round(bw));
  base.height = Math.max(1, Math.round(bh));
  const ctx = base.getContext('2d');
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, base.width, base.height);
  ctx.translate(base.width / 2, base.height / 2);
  ctx.rotate((p.straighten * Math.PI) / 180);
  ctx.scale(p.flip_h ? -1 : 1, p.flip_v ? -1 : 1);
  ctx.rotate((p.rotate * Math.PI) / 180);
  ctx.drawImage(src, -src.width / 2, -src.height / 2);
  draw();
}

function draw() {
  if (!base.width) return;
  const maxW = stage.clientWidth;
  const maxH = Math.max(240, window.innerHeight * 0.6);
  const scale = Math.min(maxW / base.width, maxH / base.height);
  const w = Math.round(base.width * scale);
  const h = Math.round(base.height * scale);
  const dpr = window.devicePixelRatio || 1;
  view = { scale, w, h };
  canvas.style.width = `${w}px`;
  canvas.style.height = `${h}px`;
  canvas.width = Math.round(w * dpr);
  canvas.height = Math.round(h * dpr);

  const ctx = canvas.getContext('2d');
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  ctx.drawImage(base, 0, 0, w, h);

  const r = { x: crop.x * w, y: crop.y * h, w: crop.w * w, h: crop.h * h };
  ctx.fillStyle = 'rgb(0 0 0 / .5)';
  ctx.beginPath();
  ctx.rect(0, 0, w, h);
  ctx.rect(r.x, r.y, r.w, r.h);
  ctx.fill('evenodd');

  // Garis sepertiga membantu meluruskan bidang oklusal / garis interpupil.
  ctx.strokeStyle = 'rgb(255 255 255 / .45)';
  ctx.lineWidth = 1;
  ctx.beginPath();
  for (const t of [1 / 3, 2 / 3]) {
    ctx.moveTo(r.x + r.w * t, r.y);
    ctx.lineTo(r.x + r.w * t, r.y + r.h);
    ctx.moveTo(r.x, r.y + r.h * t);
    ctx.lineTo(r.x + r.w, r.y + r.h * t);
  }
  ctx.stroke();
  ctx.strokeStyle = '#fff';
  ctx.lineWidth = 2;
  ctx.strokeRect(r.x, r.y, r.w, r.h);
  ctx.fillStyle = '#fff';
  for (const [cx, cy] of corners(r)) ctx.fillRect(cx - 6, cy - 6, 12, 12);

  refreshControls();
}

function corners(r) {
  return [[r.x, r.y], [r.x + r.w, r.y], [r.x, r.y + r.h], [r.x + r.w, r.y + r.h]];
}

function bind() {
  root.addEventListener('click', (e) => {
    const action = e.target.closest('[data-action]')?.dataset.action;
    const aspect = e.target.closest('[data-aspect]')?.dataset.aspect;
    if (action === 'rotate-left' || action === 'rotate-right') {
      p.rotate = (p.rotate + (action === 'rotate-right' ? 90 : 270)) % 360;
      crop = { ...FULL }; // koordinat crop relatif berubah arti setelah diputar
      renderBase();
    } else if (action === 'flip-h') {
      p.flip_h = !p.flip_h;
      p.auto_flip = false;
      crop.x = 1 - crop.x - crop.w;
      renderBase();
    } else if (action === 'flip-v') {
      p.flip_v = !p.flip_v;
      p.auto_flip = false;
      crop.y = 1 - crop.y - crop.h;
      renderBase();
    } else if (action === 'crop-reset') {
      crop = { ...FULL };
      draw();
    } else if (aspect) {
      locked = aspect === 'locked';
      if (locked) {
        // Rasio asli = w sama dengan h dalam koordinat relatif.
        const s = Math.min(crop.w, crop.h);
        crop = { x: crop.x + (crop.w - s) / 2, y: crop.y + (crop.h - s) / 2, w: s, h: s };
      }
      draw();
    }
  });

  straightenInput.addEventListener('input', () => {
    p.straighten = Number(straightenInput.value);
    renderBase();
  });

  canvas.addEventListener('pointerdown', (e) => {
    const pt = point(e);
    const r = { x: crop.x * view.w, y: crop.y * view.h, w: crop.w * view.w, h: crop.h * view.h };
    const hit = corners(r).findIndex(([cx, cy]) => Math.hypot(cx - pt.px, cy - pt.py) <= HANDLE);
    if (hit >= 0) {
      // Sudut berlawanan menjadi jangkar; arah tarikan tetap mengikuti sudut yang dipegang.
      drag = { mode: 'resize', dirX: hit % 2 ? 1 : -1, dirY: hit > 1 ? 1 : -1 };
      drag.ax = drag.dirX > 0 ? crop.x : crop.x + crop.w;
      drag.ay = drag.dirY > 0 ? crop.y : crop.y + crop.h;
    } else if (pt.x >= crop.x && pt.x <= crop.x + crop.w && pt.y >= crop.y && pt.y <= crop.y + crop.h) {
      drag = { mode: 'move', sx: pt.x, sy: pt.y, start: { ...crop } };
    } else {
      return;
    }
    canvas.setPointerCapture(e.pointerId);
    e.preventDefault();
  });

  canvas.addEventListener('pointermove', (e) => {
    if (!drag) return;
    const pt = point(e);
    if (drag.mode === 'move') {
      crop.x = clamp(drag.start.x + pt.x - drag.sx, 0, 1 - crop.w);
      crop.y = clamp(drag.start.y + pt.y - drag.sy, 0, 1 - crop.h);
    } else {
      const availX = drag.dirX > 0 ? 1 - drag.ax : drag.ax;
      const availY = drag.dirY > 0 ? 1 - drag.ay : drag.ay;
      let w = clamp((pt.x - drag.ax) * drag.dirX, MIN_CROP, availX);
      let h = clamp((pt.y - drag.ay) * drag.dirY, MIN_CROP, availY);
      if (locked) w = h = Math.max(MIN_CROP, Math.min(Math.max(w, h), availX, availY));
      crop = { x: drag.dirX > 0 ? drag.ax : drag.ax - w, y: drag.dirY > 0 ? drag.ay : drag.ay - h, w, h };
    }
    draw();
  });

  for (const type of ['pointerup', 'pointercancel']) canvas.addEventListener(type, () => { drag = null; });

  root.querySelector('[data-save-form]').addEventListener('submit', () => {
    root.querySelector('[data-params]').value = serialize();
    saveBtn.disabled = true;
    saveBtn.textContent = 'Menyimpan…';
  });
}

function point(e) {
  const rect = canvas.getBoundingClientRect();
  const px = e.clientX - rect.left;
  const py = e.clientY - rect.top;
  return { px, py, x: clamp(px / rect.width, 0, 1), y: clamp(py / rect.height, 0, 1) };
}

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
