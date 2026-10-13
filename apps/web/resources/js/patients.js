// Pencarian pasien saat mengetik (FR-01), di browser tanpa request ke server.
// Aturan cocok sama dengan PatientController::matches().
const input = document.querySelector('[data-patient-search]');
const rows = [...document.querySelectorAll('[data-search]')];
const sections = [...document.querySelectorAll('[data-section]')];
const empty = document.querySelector('[data-search-empty]');

function matches(text, q) {
  return [q, q.replaceAll('-', ''), q.replace(/[\s-]/g, '')].some((v) => v && text.includes(v));
}

function filter() {
  const q = input.value.trim().toLowerCase();
  let shown = 0;
  for (const row of rows) {
    const hit = !q || matches(row.dataset.search, q);
    row.hidden = !hit;
    if (hit) shown++;
  }
  for (const section of sections) {
    section.hidden = !rows.some((r) => !r.hidden && r.dataset.letter === section.dataset.section);
  }
  if (empty) empty.hidden = shown > 0;
}

if (input) {
  input.addEventListener('input', filter);
  input.form?.addEventListener('submit', (e) => { e.preventDefault(); filter(); });
  filter();
}
