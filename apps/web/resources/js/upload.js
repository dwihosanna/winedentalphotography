// Unggah banyak foto ke slot sesi (FR-07). Foto diunggah satu per satu ke endpoint yang sama dengan kamera.
const root = document.querySelector('[data-bulk-upload]');
const config = JSON.parse(document.getElementById('upload-config').textContent);
const csrf = document.querySelector('meta[name="csrf-token"]').content;
const list = root.querySelector('[data-list]');
const submit = root.querySelector('[data-submit]');
const done = root.querySelector('[data-done]');
const conflict = root.querySelector('[data-conflict]');
const items = [];

root.querySelector('[data-files]').addEventListener('change', (e) => {
  for (const file of e.target.files) addItem(file);
  e.target.value = '';
  refresh();
});

submit.addEventListener('click', uploadAll);

function addItem(file) {
  const isPdf = file.type === 'application/pdf';
  const taken = new Set(items.map((i) => i.select.value));
  // Saran slot: slot kosong berikutnya yang cocok jenisnya (PDF hanya untuk rontgen).
  const fit = (s) => (isPdf ? s.kind === 'xray' : true);
  const suggestion = config.slots.find((s) => fit(s) && !s.filled && !taken.has(s.code))
    ?? config.slots.find((s) => fit(s) && !taken.has(s.code));

  const li = document.createElement('li');
  li.className = 'upload-item';
  const img = document.createElement(isPdf ? 'span' : 'img');
  if (isPdf) { img.className = 'ph'; img.textContent = 'PDF'; } else { img.src = URL.createObjectURL(file); img.alt = ''; }

  const body = document.createElement('div');
  body.className = 'field';
  const name = Object.assign(document.createElement('span'), { className: 'muted', textContent: file.name });
  const select = document.createElement('select');
  select.className = 'select';
  select.append(new Option('Lewati file ini', ''));
  for (const s of config.slots) {
    if (!fit(s)) continue;
    select.append(new Option(`${s.code} ${s.label}${s.filled ? ' (ganti foto)' : ''}`, s.code));
  }
  select.value = suggestion?.code ?? '';
  select.addEventListener('change', refresh);
  const state = Object.assign(document.createElement('span'), { className: 'state' });
  body.append(name, select, state);
  li.append(img, body);
  list.append(li);
  items.push({ file, li, select, state, done: false });
}

function refresh() {
  const codes = items.filter((i) => !i.done && i.select.value).map((i) => i.select.value);
  conflict.hidden = new Set(codes).size === codes.length;
  submit.hidden = codes.length === 0;
}

async function uploadAll() {
  submit.disabled = true;
  window.addEventListener('beforeunload', warn);
  for (const item of items) {
    if (item.done || !item.select.value) continue;
    item.li.classList.remove('error');
    item.state.textContent = 'Mengunggah…';
    try {
      const form = new FormData();
      form.append('file', item.file);
      form.append('slot_code', item.select.value);
      form.append('source', 'upload');
      const res = await fetch(config.uploadUrl, { method: 'POST', body: form, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(firstError(json) || `Gagal (HTTP ${res.status})`);
      item.done = true;
      item.li.classList.add('done');
      item.select.disabled = true;
      item.state.textContent = json.exif_date_mismatch ? 'Terunggah. Tanggal foto jauh dari tanggal sesi; periksa sesinya.' : 'Terunggah';
    } catch (e) {
      item.li.classList.add('error');
      item.state.textContent = e.message;
    }
  }
  window.removeEventListener('beforeunload', warn);
  submit.disabled = false;
  const remaining = items.some((i) => !i.done && i.select.value);
  submit.textContent = remaining ? 'Coba lagi yang gagal' : 'Unggah semua';
  done.hidden = false;
  refresh();
}

function firstError(json) {
  return json.errors ? Object.values(json.errors)[0]?.[0] : json.message;
}

function warn(e) { e.preventDefault(); e.returnValue = ''; }
