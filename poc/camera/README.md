# PoC kamera – Mode A

Halaman statis untuk Fase 0 (SRS Bagian 11.2): kamera browser (`getUserMedia`) dengan garis bantu per slot,
waterpass, panel diagnostik, dan ekspor ZIP. Tidak ada server dan database; foto hanya ada di HP sampai diekspor.

## Isi folder

| File | Fungsi |
| --- | --- |
| `index.html` | Kerangka halaman |
| `app.js` | Kamera, overlay, capture, IndexedDB, ekspor ZIP |
| `style.css` | Tampilan |
| `slots.v1.json` | 8 slot + garis bantu (koordinat 0..1). Ubah di sini, bukan di kode |

## Deploy (wajib HTTPS)

Kamera web di iPhone hanya jalan di HTTPS. Pilih salah satu:

- **Cloudflare Pages / Netlify / Vercel** yang terhubung ke repo GitHub: root directory `poc/camera`,
  build command kosong, output directory `.` (atau `poc/camera`). Preview per pull request otomatis.
- **GitHub Pages** lewat workflow `.github/workflows/poc-pages.yml`. Di repo privat, Pages biasanya butuh
  paket GitHub berbayar (perlu dicek di Settings > Pages).

Tidak ada build step. Satu-satunya dependensi eksternal adalah JSZip dari cdnjs.

## Cara uji di iPhone

1. Buka URL deploy di Safari.
2. Isi ID subjek (relawan, mis. `R01`) dan pilih perangkat, lalu **Mulai kamera** dan izinkan kamera.
3. Buka **Pengaturan**: catat daftar kamera di Diagnostik, coba ganti lensa dan resolusi.
4. Aktifkan **waterpass** (iOS akan meminta izin sensor gerak).
5. Ambil 3 foto per slot. Slot intraoral otomatis meminta HP diputar ke landscape.
6. **Siapkan ZIP** lalu **Bagikan ZIP** ke AirDrop/Files. Ekspor setelah setiap subjek.
7. Ulangi di mode PWA: Share > Add to Home Screen, buka dari ikon, lalu bandingkan perilaku izin kamera.
8. Setelah ZIP aman di laptop, **Hapus foto subjek ini dari HP**.

## Yang dicatat per foto (`log.csv`)

Resolusi asli frame, label kamera, preset yang diminta, zoom/fokus/torch, orientasi, nilai waterpass,
waktu sejak slot dibuka, waktu capture, mode standalone, ketersediaan `ImageCapture`, versi overlay.

## Batasan yang diketahui

- Foto = frame video, bukan foto resolusi penuh kamera iPhone. Mengukur selisih ini adalah tujuan PoC.
- Arah anterior pada IO-01/IO-03 dan posisi garis bantu adalah perkiraan awal dari template klinik;
  dokter perlu mengoreksi `slots.v1.json` setelah uji pertama, lalu naikkan `version`.
- Foto oklusal (cermin) disimpan apa adanya; flip vertikal hanya dicatat (`mirror_required`), belum diterapkan.
- iOS dapat menghapus data situs yang jarang dibuka. Jangan tunda ekspor.
- Jangan pakai foto pasien di PoC ini.
