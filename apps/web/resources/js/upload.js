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
  // Saran slot: slot kosong berikutnya yang cocok jenisnya (PDF hanya untuk rontgen). Slot FREE muat beberapa foto.
  const fit = (s) => (isPdf ? s.kind === 'xray' : true);
  const free = (s) => room(s) > pending(s.code);
  const suggestion = config.slots.find((s) => fit(s) && !s.filled && free(s))
    ?? config.slots.find((s) => fit(s) && s.max === 1 && !pending(s.code));

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
    const note = s.max > 1 ? ` (${s.count}/${s.max})` : s.filled ? ' (ganti foto)' : '';
    select.append(new Option(`${s.code} ${s.label}${note}`, s.code));
  }
  select.value = suggestion?.code ?? '';
  select.addEventListener('change', refresh);
  const state = Object.assign(document.createElement('span'), { className: 'state' });
  body.append(name, select, state);
  li.append(img, body);
  list.append(li);
  items.push({ file, li, select, state, done: false });
}

/* Sisa tempat di slot: slot biasa 1 (foto baru mengganti), slot FREE sampai max dikurangi foto yang sudah ada. */
function room(slot) {
  return slot.max > 1 ? slot.max - slot.count : 1;
}

function pending(code) {
  return items.filter((i) => !i.done && i.select.value === code).length;
}

function refresh() {
  const codes = items.filter((i) => !i.done && i.select.value).map((i) => i.select.value);
  conflict.hidden = config.slots.every((s) => pending(s.code) <= room(s));
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
      const slot = config.slots.find((s) => s.code === item.select.value);
      if (slot.max > 1) slot.count++;
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
