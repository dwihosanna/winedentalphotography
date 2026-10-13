# Aplikasi web – Wine Dental Photo

Aplikasi Laravel 13 (MVC: Eloquent + Blade + controller) untuk MVP. Spesifikasi: [`docs/Srs.md`](../../docs/Srs.md).
Target hosting: Hostinger Cloud Startup (PHP 8.3+, MySQL). Kode menjaga kompatibilitas PHP 8.3 (`config.platform.php` di `composer.json`).

## Menjalankan lokal

```sh
brew services start mysql
cp .env.example .env && php artisan key:generate   # isi DB_PASSWORD
php artisan migrate --seed                          # slot_templates + akun dokter@example.com / password (lokal saja)
php artisan test                                    # memakai database dental_photo_test
npm install && npm run build                        # CSS tema (packages/theme) + JS
php artisan serve                                   # http://localhost:8000
```

Login Google butuh `GOOGLE_CLIENT_ID` dan `GOOGLE_CLIENT_SECRET` di `.env`; tanpa itu tombol Google disembunyikan.
Email lokal (verifikasi, lupa kata sandi) ditulis ke `storage/logs/laravel.log` (`MAIL_MAILER=log`).

Database lokal: `dental_photo` dan `dental_photo_test`, user `dental`. Tes berjalan di MySQL, bukan SQLite,
agar foreign key gabungan dan perilaku MySQL sama dengan produksi.

## Keputusan desain

- **Isolasi workspace tanpa row-level security.** Model data pasien memakai trait `BelongsToWorkspace`:
  semua query dibatasi ke workspace aktif (`App\Support\CurrentWorkspace`), dan query tanpa workspace aktif
  ditolak. Middleware `workspace` menetapkan workspace dari sesi login dan berjalan sebelum route model binding,
  sehingga ID dari workspace lain menghasilkan 404. Di database, tabel anak merujuk induknya lewat foreign key
  gabungan `(workspace_id, id)`, jadi baris tidak bisa tertaut ke workspace lain walaupun scope dilewati.
  Akses lintas workspace (admin platform) harus eksplisit: `withoutGlobalScope(WorkspaceScope::class)`.
- **File = disk + path.** Tabel `photos`, `consents`, `reports` menyimpan nama disk dan path, bukan URL. Sekarang disk
  hosting (privat); pindah ke object storage cukup menambah disk S3, menyalin file, lalu mengubah kolom `disk`.
- **Foto asli tidak pernah diubah.** Edit disimpan sebagai `edit_params` + file edit terpisah; retake membuat baris
  baru dan mengisi `replaced_by_photo_id` pada foto lama.
- **Aturan slot** ada di `config/slots.php`, disalin ke tabel `slot_templates` oleh `SlotTemplateSeeder`.
- **Antrian** memakai driver `database` dan dijalankan cron (`schedule:run` tiap menit), karena paket hosting
  tidak menjalankan worker terus-menerus.
- **PWA** (NFR-01): `public/manifest.webmanifest`, ikon di `public/icons/` (sumber: `icon.svg`), service worker
  `public/sw.js`. Halaman HTML tidak pernah di-cache karena berisi data pasien; saat offline tampil `offline.html`.
  Hanya aset build, ikon, dan manifest yang di-cache. Versi baru menunggu pengguna menekan "Muat ulang" di banner
  (`resources/js/pwa.js`); naikkan `VERSION` di `sw.js` setiap kali file itu berubah. Service worker hanya jalan
  di HTTPS (atau localhost), jadi domain Hostinger wajib memakai SSL.
- **Audit log** append-only di level model. Trigger MySQL tidak dipakai karena hak TRIGGER di hosting belum pasti.

| Tabel | Model | SRS Bagian 7 |
| --- | --- | --- |
| `workspaces`, `workspace_members` | `Workspace`, `WorkspaceMember` | workspace, workspace\_member |
| `terms_acceptances`, `dentist_verifications` | `TermsAcceptance`, `DentistVerification` | terms\_acceptance, dentist\_verification |
| `patients`, `patient_external_ids` | `Patient`, `PatientExternalId` | patient, patient\_external\_id |
| `ortho_cases`, `photo_sessions` | `OrthoCase`, `PhotoSession` | ortho\_case, session |
| `slot_templates`, `photos` | `SlotTemplate`, `Photo` | slot\_template, photo |
| `consents`, `reports`, `audit_logs` | `Consent`, `Report`, `AuditLog` | consent, report, audit\_log |
