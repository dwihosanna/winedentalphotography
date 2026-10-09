# PoC kamera – Mode A

Halaman statis untuk Fase 0 (SRS Bagian 11.2): kamera browser (`getUserMedia`) dengan garis bantu per slot,
waterpass, panel diagnostik, dan ekspor ZIP. Tidak ada server dan database; foto hanya ada di HP sampai diekspor.

## Isi folder

| File | Fungsi |
| --- | --- |
| `index.html` | Kerangka halaman |
| `app.js` | Kamera, overlay, capture, IndexedDB, ekspor ZIP |
| `style.css` | Tampilan |
| `slots.v2.json` | 11 slot (8 wajib + 3 tambahan) dan garis bantu. **Dibuat oleh generator**, jangan diedit manual |
| `tools/gen_slots.py` | Generator garis bantu gigi/wajah. Ubah ukuran/posisi di sini lalu jalankan `python3 tools/gen_slots.py` |

## Perubahan v2 (masukan dokter setelah uji pertama)

- Garis bantu intraoral kini hanya outline posisi gigi (tanpa garis dan label); gigi bawah tersembunyi di belakang gigi atas.
- EO-03 memakai siluet kepala menghadap ke kanan.
- Oklusal atas/bawah diambil dalam posisi **portrait**: gigi seri atas di atas layar, gigi seri bawah di bawah layar.
- Tombol **Cermin**: preview dan hasil foto dibalik (vertikal untuk oklusal, horizontal untuk lateral). Default aktif untuk oklusal.
- Slot tambahan: IO-06 anterior atas, IO-07 anterior bawah, FREE tanpa garis bantu (tidak dihitung target 3 foto).
- **Galeri**: ketuk tombol di kiri shutter. Foto dikelompokkan per slot; ketuk untuk ukuran penuh, geser kiri/kanan, hapus per foto.
- Database lokal naik ke versi 2 (tambah thumbnail). Foto lama tetap ada; thumbnail dibuat saat galeri pertama kali dibuka.
- `slots.v1.json` tidak dipakai lagi dan boleh dihapus dari repo.

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
- Ukuran dan posisi outline gigi adalah perkiraan generik; dokter mengoreksi lewat `tools/gen_slots.py`, lalu naikkan `VERSION`.
- IO-01/IO-03 mengikuti tampak langsung: gigi depan di sisi kiri layar untuk sisi kiri pasien, di kanan untuk sisi kanan.
- Arah flip cermin (vertikal untuk oklusal) perlu dikonfirmasi saat uji; bila hasilnya terbalik, ubah `axis` slot di `tools/gen_slots.py`.
- iOS dapat menghapus data situs yang jarang dibuka. Jangan tunda ekspor.
- Jangan pakai foto pasien di PoC ini.
