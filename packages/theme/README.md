# Tema Wine Dental Aesthetic

CSS dasar untuk aplikasi dental photography (`apps/web`, Fase 1). Warna dan font diambil dari
[winedentalaesthetic.com](https://winedentalaesthetic.com/) agar aplikasi satu identitas dengan website klinik.

| File | Isi |
| --- | --- |
| `theme.css` | Token warna/tipografi (terang + gelap), elemen dasar, dan komponen layar MVP |
| `fonts/` | Jost dan Cormorant Garamond (woff2 variable, subset latin) + lisensi OFL |
| `preview.html` | Pratinjau dashboard, sheet pasien, arsip, dan komponen dengan data contoh |

Buka `preview.html` di browser untuk melihat tema. Tombol Terang/Gelap/Ikuti HP mengganti mode.

## Warna

| Token | Nilai | Pemakaian |
| --- | --- | --- |
| `--wine` | `#6B1E2E` | Warna utama: bar atas, tombol utama, judul |
| `--merlot` | `#3A0E18` | Hover tombol; latar mode gelap |
| `--rose` | `#E9CFCF` | Latar avatar/label tahap; tombol utama di mode gelap |
| `--porcelain` | `#FBF8F6` | Latar terang |
| `--gold` | `#B08D57` | **Hanya garis dan aksen** (bingkai avatar, label DPID). Kontras 2,9:1, jangan untuk teks |
| `--ink` | `#2A1518` | Teks |

Komponen memakai token peran (`--bg`, `--surface`, `--text`, `--primary`, `--muted`, ...), bukan warna merek langsung,
sehingga mode gelap cukup mengganti token. Semua pasangan teks/latar lolos WCAG AA (≥ 5,4:1).
Warna status (`--success`, `--warning`, `--danger`) selalu disertai teks atau ikon karena merah bahaya mirip wine.

## Font

- **Jost** (300–600): semua teks antarmuka. Teks kecil memakai 400, bukan 300 seperti website, agar terbaca di HP.
- **Cormorant Garamond** (400–600): hanya judul ≥ 22px, nama pasien di sheet, dan angka tanggal di Arsip.
- Font di-host sendiri. Jangan memuat dari Google Fonts: setiap muat halaman mengirim alamat IP pengguna ke Google.

## Layar kamera

Layar kamera tidak memakai warna merek sebagai latar. Latar tetap hitam netral (`--camera-bg`) dan garis bantu
tetap amber (`--guide`), karena warna di sekitar preview memengaruhi penilaian warna gusi dan gigi.
