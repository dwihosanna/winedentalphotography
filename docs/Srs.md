# Software Requirement Specification — Sistem Dental Photography Ortodonti

Oct 8, 2026 · @Winners

**Revisi 10 Okt 2026:** aplikasi berdiri sendiri untuk dokter gigi mana pun (daftar akun + login Google, verifikasi dokter), pasien dikunci oleh **Dental Photo ID** (No. RM menjadi opsional), dashboard daftar pasien, sheet pasien dengan Galeri dan Arsip. Lihat 5.2–5.4, FR-27 s.d. FR-33, dan dampaknya di Bagian 7, 9, 10. MVP: dokter klinik memakai akun **Premium** yang diatur admin; dokter luar bisa mencoba akun **Free** (maks. 50 pasien, 5 GB, tanpa asisten) dan naik ke Premium setelah diverifikasi admin developer. Gratis selama masa uji 1–2 tahun; belum ada pembayaran.

## 1. Ringkasan eksekutif

Sistem ini adalah PWA untuk mendokumentasikan foto ortodonti per pasien (dikunci oleh No. RM), dari awal pemasangan, setiap kontrol, sampai lepas behel, lalu menghasilkan laporan before/after dengan format template case report klinik (windetalaesthetic).

**Tujuan bisnis**

- Foto setiap kontrol konsisten (posisi, jarak, sudut) sehingga perbandingan before/after valid secara klinis, bukan sekadar "foto bagus".
- Arsip terstruktur otomatis: pasien, tanggal, tahap (awal/kontrol/lepas), kategori foto.
- Report PDF case report dalam hitungan menit, bukan menyusun manual di Canva/PowerPoint.

**Ruang lingkup v1**: aplikasi mandiri multi-dokter (registrasi, login Google, verifikasi dokter gigi), manajemen pasien via Dental Photo ID dengan No. RM opsional (manual + opsional integrasi RME), capture terpandu dengan overlay, upload foto/rontgen, galeri per kategori dan timeline, perbandingan side-by-side, generator report.

**Di luar ruang lingkup v1**: analisis sefalometri (tracing landmark), simulasi senyum/AI smile design, pemantauan jarak jauh oleh pasien, integrasi DICOM langsung ke mesin rontgen.

**Catatan kritis paling penting (dibaca dulu)**

1. **Kamera browser adalah titik terlemah rencana PWA, bukan detail kecil.** Foto intraoral klinis biasanya memakai DSLR/mirrorless + lensa makro + ring/twin flash. Kamera HP lewat `getUserMedia` di browser umumnya memberi resolusi frame video (bukan resolusi foto penuh), tanpa kontrol fokus/eksposur manual di iOS, dan tanpa flash. Untuk ekstraoral (wajah) ini biasanya cukup; untuk intraoral, terutama oklusal via cermin, kualitasnya berisiko kurang. \[medium confidence — berdasarkan pengetahuan umum keterbatasan WebRTC di Safari; detail dukungan API perlu diverifikasi live, lihat Bagian 8\] Karena semua perangkat klinik adalah iPhone, risiko ini kini menjadi keputusan arsitektur utama (Bagian 8.0).
2. **Mode "upload" harus diperlakukan sebagai jalur utama yang setara, bukan fallback.** Banyak klinik tetap memotret dengan DSLR; sistem harus menerima foto itu dan tetap memberi tag kategori serta alat bantu crop/rotate/mirror-flip yang sama dengan hasil capture langsung.
3. **Overlay guide hanya menjamin komposisi, bukan standarisasi.** Konsistensi sejati juga butuh jarak kamera, pencahayaan, dan retraktor/cermin yang sama. Requirement mencakup SOP non-software (Bagian 6) karena tanpa itu fitur overlay tidak akan menghasilkan before/after yang valid.
4. **Data foto wajah + rontgen adalah data kesehatan dan data biometrik.** Ini memicu kewajiban UU PDP dan aturan RME, termasuk persetujuan pasien untuk penggunaan foto di report/media sosial. Ini bukan fitur tambahan; ini syarat go-live (Bagian 9).

## 2. Benchmark aplikasi yang sudah ada

Pola yang berulang di aplikasi pembanding: capture terpandu (grid/overlay), urutan foto otomatis masuk ke layout standar, ghost overlay dari foto sebelumnya, dan layout before/after siap pakai. Tidak satu pun yang ditemukan menawarkan template case report persis seperti milik klinik, jadi itu pembeda utama sistem ini.

| Aplikasi | Jenis | Fitur relevan | Diadopsi ke sistem ini |
| --- | --- | --- | --- |
| [Dental Shooting](https://apps.apple.com/app/id1600504404) | App iOS/Android, langganan | Guide grid untuk foto intra/ekstraoral yang repeatable; edit real-time; sinkron ke desktop; [PDF otomatis dan share via WhatsApp/email](https://apps.apple.com/app/id1600504404) | Guide overlay per kategori, PDF report, share via WhatsApp |
| [DentalFolio](https://apps.apple.com/app/id6758618866) | App iOS | Capture di app atau import dari galeri/DSLR; setiap foto masuk ke file pasien/case; tag yang bisa dicari; layout before/after rata; grid overlay kolase | Upload DSLR setara capture, tag, layout before/after yang sejajar |
| [RxPhoto](https://kb.rxphoto.com/portal/en/kb/articles/photoghost-from-the-photos-screen) | App klinik estetik | Ghost overlay: foto "before" semi-transparan dengan slider transparansi; ghost berurutan untuk satu set foto | **Ghost overlay foto kontrol sebelumnya** — fitur paling berdampak untuk konsistensi kontrol |
| [Dolphin Imaging Plus](https://sourceforge.net/software/orthodontist/integrates-with-sql-server/) | Software desktop ortodonti | Foto diambil berurutan lalu otomatis disusun ke layout standar ekstraoral/intraoral/oklusal; drag-and-drop; [tracing sefalometri, superimposisi](https://us.fitgap.com/products/dolphin-imaging) | Capture berurutan → auto-layout. Tracing sefalometri **tidak** di v1 |
| [AltaSmiles V5X](https://apps.apple.com/app/id6476243375) | App pasien (iPhone) | Photo guide + overlay, timeline progres, berbagi ke dokter | Timeline per pasien |
| [ProgressCam](https://mwm.ai/apps/progresscam-progress-photos/6760968159) | App umum (non-dental) | Ghost overlay + smart alignment; side-by-side, slide-to-reveal, tap-to-toggle | Mode perbandingan: side-by-side, slider, toggle |
| [Smile Lite MDP](https://curion.ca/products/smile-lite-mdp) | Hardware lampu untuk smartphone | Lampu LED "mini studio" untuk HP mana pun; tidak butuh app khusus | Bukan software, tapi sinyal bahwa **pencahayaan**, bukan app, adalah bottleneck kualitas foto HP |

**Insight dari paten terkait.** [Paten Align Technology US 11191617](https://patents.justia.com/patent/11191617) menyebut standar klasik memakai DSLR dengan lensa 90–100 mm dan flash melingkar, serta bahwa foto HP yang terlalu jauh dari gigi gagal memenuhi standar ortodonti. Paten itu juga membedakan overlay generik dan overlay spesifik pasien. Implikasinya: sistem ini sebaiknya mulai dengan overlay generik (v1), lalu ghost overlay dari foto pasien sendiri (v1.1). Hati-hati dengan fitur yang terlalu mirip klaim paten pihak ketiga; \[this needs verification oleh konsultan HKI bila produk akan dijual komersial\].

**Keterbatasan riset ini.** Data fitur di atas berasal dari halaman listing App Store dan situs pihak ketiga, bukan uji coba langsung aplikasinya. \[medium confidence\] Harga dan fitur terbaru bisa berubah; perlu verifikasi live sebelum dipakai sebagai dasar keputusan beli vs bangun.

**Pertanyaan yang harus dijawab sebelum membangun:** apakah membeli langganan Dental Shooting/DentalFolio + template PDF sendiri sudah memenuhi 80% kebutuhan? Nilai unik sistem custom ada pada integrasi No. RM/RME dan template case report klinik. Jika integrasi RME tidak terjadi di v1, alasan membangun sendiri melemah.

## 3. Standar foto klinis yang menjadi acuan

Template case report klinik (8 foto: 3 ekstraoral + 5 intraoral) sudah selaras dengan seri standar American Board of Orthodontics (ABO), jadi aturan ABO dipakai sebagai acuan teknis guide dan validasi. \[high confidence — dicocokkan langsung dengan [halaman ABO Photograph Requirements](https://americanboardortho.com/orthodontists/become-certified/clinical-exam/mail-in-cre-submission-procedure/case-record-preparation/photograph-requirements/)\]

| Kategori | Aturan ABO | Implikasi ke sistem |
| --- | --- | --- |
| Semua foto | Metode foto pada rekaman awal harus diulang pada rekaman berikutnya; resolusi dan skala sama | Simpan parameter capture (perangkat, zoom, orientasi) per foto dan tampilkan ulang saat kontrol |
| Ekstraoral frontal | Dua foto depan: bibir rileks dan senyum; kepala pada Frankfort horizontal; telinga terlihat; mata terbuka menatap lurus, kacamata dilepas | Overlay garis interpupil horizontal, garis tengah wajah, siluet kepala; checklist "kacamata dilepas, telinga terlihat" |
| Ekstraoral profil | Satu foto lateral **menghadap ke kanan** | Overlay garis Frankfort (porion–orbitale) horizontal, siluet profil |
| Latar belakang | Putih atau terang, tanpa bayangan | Sesuai catatan template klinik (wajib putih); deteksi kecerahan latar sebagai peringatan |
| Skala wajah | Kira-kira 1/4 ukuran asli | Overlay batas hairline–dagu pada tinggi frame yang tetap |
| Intraoral | Frontal dalam interkuspasi maksimal; lateral kanan dan kiri; oklusal atas dan bawah; orientasi pada bidang oklusal; sedekat mungkin 1:1 | Overlay garis bidang oklusal horizontal di tengah frame, garis midline |
| Foto via cermin | Ditampilkan terbalik (mirror) seolah melihat pasien langsung | **Auto-flip** untuk oklusal dan lateral yang diambil via cermin, plus tombol flip manual |
| Kebersihan foto | Tanpa retraktor, label, jari terlihat; tanpa saliva/gelembung; dua retraktor dipakai | Checklist pra-capture; crop manual saat review |

**Temuan kritis pada template klinik**

1. **Label "Side Profile – Hadap Kiri" ambigu.** Pada contoh foto, wajah pasien mengarah ke sisi kanan frame, yang sama dengan "facing to the right" versi ABO. Label "hadap kiri" kemungkinan merujuk sudut pandang fotografer. \[medium confidence — interpretasi visual dari satu contoh\] Keputusan klinik: ikuti standar ABO. Sistem memakai definisi tunggal yang tidak ambigu, mis. "Profil kanan pasien — wajah menghadap ke kanan frame", dan overlay yang menunjukkan arah itu secara visual.
2. **Template "Foto Setelah Insersi" tidak memuat foto ekstraoral.** Jika report before/after butuh perbandingan wajah, foto ekstraoral harus diambil saat kontrol/lepas juga. Ini keputusan klinis, bukan teknis.
3. **Rontgen di template adalah gambar asli dari mesin rontgen.** Memotret layar monitor rontgen dengan HP menurunkan kualitas diagnostik dan menimbulkan pantulan; requirement menetapkan rontgen **wajib upload file** (JPG/PNG/PDF; DICOM opsional di fase berikutnya).
4. **Tidak ada foto tambahan yang umum dipakai** (mis. senyum 3/4, close-up overjet). Sistem dibuat agar template bisa dikonfigurasi, bukan hardcode 8 foto.

## 4. Aktor dan alur bisnis

Satu kasus ortodonti menghasilkan rangkaian sesi foto bertahap; report before/after menarik foto dari dua sesi yang dipilih dokter, biasanya Awal dan Lepas, tetapi bisa juga Awal dan Kontrol tertentu untuk report progres.

&#91;embedded content: alur bisnis · pasien sampai report\]

Garis putus-putus menunjukkan bahwa sesi Awal adalah sumber foto "before" default; sesi Kontrol berulang sampai behel dilepas.

| Aktor | Peran dalam alur |
| --- | --- |
| Dokter (pemilik workspace) | Mendaftar akun, diverifikasi sebagai dokter gigi; membuat pasien dan kasus, memeriksa foto, generate report; mengundang asisten; mengatur template dan integrasi RME praktiknya |
| Asisten/perawat | Diundang dokter ke workspace; menyiapkan pasien, mengambil/mengunggah foto per slot, mengunggah rontgen |
| Admin platform | Memverifikasi akun dokter (STR/SIP); tidak melihat data pasien |
| Sistem RME (eksternal) | Sumber identitas pasien berdasarkan No. RM (opsional) |
| Pasien | Subjek foto; memberi consent dokumentasi/edukasi/publikasi |

## 5. Kebutuhan fungsional

Ada 34 kebutuhan fungsional di 8 modul; 28 di antaranya MVP (diperbarui setelah konfirmasi klinik: FR-22 naik ke MVP, FR-26 ditambahkan; revisi 10 Okt: FR-01/02/03/23/24 diubah, FR-27 s.d. FR-33 ditambahkan; akun Free/Premium: FR-28 kembali ke MVP sebagai syarat upgrade, FR-34 ditambahkan). Prioritas di kolom terakhir bisa diubah langsung.

| ID | Modul | Kebutuhan | Kriteria penerimaan | Prioritas |
| --- | --- | --- | --- | --- |
| FR-01 | Pasien | Cari pasien dengan nama, Dental Photo ID, No. RM, atau no. HP (lihat FR-31) | Hasil muncul < 2 detik saat mengetik; tidak ditemukan → tawarkan "tambah pasien" | MVP |
| FR-02 | Pasien | Sheet pasien baru: nama, tanggal lahir, email, no. HP; No. RM dan jenis kelamin opsional. Email dan no. HP hanya data kontak/identitas; aplikasi tidak mengirim apa pun ke pasien | Wajib: nama + tanggal lahir + salah satu kontak; nama + tanggal lahir sama di workspace yang sama → peringatan kemungkinan duplikat; No. RM bila diisi unik per workspace | MVP |
| FR-03 | Pasien | Sinkron No. RM dan identitas dari RME (opsional, per workspace), mis. ERP GAS + Google Sheets via Sheets API (lihat 5.1) | Field hasil sinkron read-only + waktu sinkron; gagal → tetap bisa input manual; Dental Photo ID tidak pernah berubah karena sinkron | v1.1 |
| FR-04 | Kasus | Buat kasus ortodonti per pasien: tipe behel, tanggal pemasangan, dokter PJ, status (aktif/selesai) | Satu pasien bisa > 1 kasus (mis. retreatment) | MVP |
| FR-05 | Kasus | Buat sesi per kunjungan dengan tahap: Awal, Insersi, Kontrol ke-n, Lepas, Retensi | Nomor kontrol otomatis; tanggal sesi default hari ini, bisa diubah | MVP |
| FR-06 | Capture | Capture langsung dengan kamera + overlay guide per kategori | Lihat Bagian 6 | MVP |
| FR-07 | Capture | Upload foto dari galeri/DSLR (multi-file) lalu tetapkan kategori | Upload hingga 11 foto sekaligus (8 wajib + 3 tambahan); kategori bisa diatur via drag ke slot | MVP |
| FR-08 | Capture | Upload rontgen (panoramik, sefalometri) | Hanya upload; format JPG/PNG/PDF; tanpa mode kamera | MVP |
| FR-09 | Capture | Checklist sesi menampilkan slot terisi/kosong sesuai template tahap | Sesi bisa disimpan draft; status "lengkap" hanya bila slot wajib terisi | MVP |
| FR-10 | Capture | Retake dan ganti foto pada slot | Foto lama tidak hilang, ditandai "diganti" (audit) | MVP |
| FR-11 | Capture | Ghost overlay: foto kategori sama dari sesi sebelumnya tampil semi-transparan | Slider opasitas 0–70%; default dari sesi kontrol terakhir | v1.1 |
| FR-12 | Capture | Antrian offline: foto tersimpan lokal saat internet putus, unggah otomatis saat online | Tidak ada foto hilang saat app ditutup; indikator "belum terunggah" | v1.1 |
| FR-13 | Review | Edit non-destruktif: crop, rotate, luruskan, flip horizontal/vertikal | File asli selalu tersimpan; edit bisa di-reset | MVP |
| FR-14 | Review | Auto-flip untuk kategori yang diambil via cermin (oklusal atas/bawah, lateral via cermin) | Sesuai aturan ABO soal foto cermin; bisa dimatikan per foto | MVP |
| FR-15 | Galeri | Tampilan per pasien dikelompokkan per kategori (kiri, kanan, depan, oklusal atas, oklusal bawah, wajah, rontgen) | Filter kategori + tahap + rentang tanggal | MVP |
| FR-16 | Galeri | Timeline per kategori: semua foto kontrol berurutan | Swipe antar sesi; label tanggal + tahap di tiap foto | v1.1 |
| FR-17 | Galeri | Perbandingan dua foto: side-by-side | Pilih 2 sesi mana pun untuk kategori sama | MVP |
| FR-18 | Galeri | Metadata otomatis tiap foto: pasien\_id, kasus\_id, sesi\_id, tanggal, tahap, kategori, sumber (kamera/upload), pengambil | Tanpa input manual; lihat Bagian 7 | MVP |
| FR-19 | Galeri | Mode perbandingan slider dan toggle | Slider vertikal geser; ketuk untuk toggle | v1.1 |
| FR-20 | Report | Generate case report sesuai template klinik (halaman profil wajah + intraoral, rontgen, setelah insersi) | Header terisi otomatis: nama, tanggal pasang/lepas, tipe behel; logo klinik | MVP |
| FR-21 | Report | Report before/after: dokter memilih sesi "before" dan "after" lalu memilih foto per slot | Preview sebelum export; export PDF A4 | MVP |
| FR-22 | Report | Opsi anonimisasi untuk edukasi/media sosial: sembunyikan nama, RM, dan (opsional) blur/crop mata | Hanya bisa dipakai bila consent publikasi pasien tercatat | MVP |
| FR-23 | Keamanan | Login per pengguna + peran per workspace: Dokter (pemilik), Asisten; Admin platform terpisah | Asisten bisa capture/upload; hanya dokter generate/export report; data pasien terisolasi per workspace | MVP |
| FR-24 | Integrasi | Kirim balik foto/report ke RME memakai Dental Photo ID + No. RM sebagai kunci | Tergantung API RME yang dipakai; perlu spesifikasi vendor | v2 |
| FR-25 | Keamanan | Audit log: siapa melihat, mengunggah, mengubah, mengekspor | Log tidak bisa dihapus pengguna klinik | MVP |
| FR-26 | Report | Template report tambahan, minimal report progres untuk pasien (foto kontrol terakhir vs awal per kategori); template bisa dikonfigurasi admin | Dokter memilih template saat generate; template baru tidak butuh rilis ulang aplikasi | MVP |
| FR-27 | Akun | Registrasi dan login dokter: email + kata sandi (verifikasi email) atau Login dengan Google (SSO). Akun baru = **Free**. Saat daftar, dokter menyatakan dirinya dokter gigi berizin dan menyetujui syarat layanan | Satu akun per email; email harus terverifikasi sebelum membuat pasien; persetujuan syarat layanan tercatat (versi + waktu); sesi login bisa dicabut; lupa kata sandi lewat email | MVP |
| FR-28 | Akun | Upgrade ke **Premium**: dokter mengajukan dengan unggah STR (dan SIP bila ada); admin platform meninjau lalu mengubah status. Admin juga bisa langsung menetapkan Premium (dokter klinik) | Status pengajuan menunggu/disetujui/ditolak + alasan; perubahan status akun tercatat di audit log (siapa, kapan) | MVP |
| FR-34 | Akun | Batas akun Free: **maks. 50 pasien** dan **5 GB penyimpanan** per workspace; Premium tanpa batas. Pemakaian tampil di menu akun. Admin bisa menangguhkan akun | Pasien ke-51 ditolak dengan ajakan upgrade; saat 5 GB tercapai, foto/rontgen baru ditolak tetapi foto lama tetap bisa dilihat dan diekspor; peringatan di 80% dan 100%; yang dihitung: file asli + hasil edit + rontgen (thumbnail tidak); pasien demo tidak dihitung; batas dicek di server; akun ditangguhkan = hanya baca, data tetap disimpan (retensi) | MVP |
| FR-29 | Akun | Workspace praktik: dokter pemilik akun **Premium** mengundang asisten lewat email. Akun Free tidak bisa mengundang asisten | Asisten hanya melihat pasien workspace yang mengundangnya; undangan bisa dicabut; tombol undang tidak tersedia di akun Free | MVP |
| FR-30 | Pasien | Dental Photo ID (DPID) dibuat otomatis untuk setiap pasien (lihat 5.2) | Unik global, tidak pernah berubah, tidak diturunkan dari data pribadi; tampil di sheet pasien; bisa disalin | MVP |
| FR-31 | Pasien | Dashboard dokter: daftar pasien urut nama, dikelompokkan per huruf + indeks A–Z, kolom pencarian, tombol tambah pasien (lihat 5.4) | 1.000 pasien tetap lancar digulir; pencarian sesuai FR-01 | MVP |
| FR-32 | Pasien | Sheet pasien: foto profil (EO-01 terbaru), nama, tanggal lahir, email, no. HP, DPID, No. RM; menu Galeri dan Arsip; tombol + mulai sesi foto; tombol Ubah | Ubah data tercatat di audit log; DPID tidak bisa diubah | MVP |
| FR-33 | Galeri | Arsip pasien: daftar sesi urut tanggal kontrol terbaru, tiap baris berisi tanggal, tahap, keterangan dokter, jumlah foto; ketuk untuk detail foto sesi | Keterangan bisa diubah dokter; sesi draft ditandai | MVP |

**Peran pengguna**

- **Dokter (pemilik workspace)**: semua akses pada pasien workspace-nya, generate dan export report, undang/cabut asisten, kelola template dan integrasi RME.
- **Asisten/perawat**: buat sesi, capture, upload, review di workspace yang mengundangnya; tidak bisa export atau menghapus.
- **Admin platform**: verifikasi akun dokter; tidak melihat data pasien.

**Catatan kritis.** FR-11 (ghost overlay) ditaruh v1.1 bukan karena kurang penting, tetapi karena butuh data sesi sebelumnya; pada minggu-minggu pertama setelah go-live belum ada foto pembanding. Jika klinik memigrasi arsip foto lama lewat upload, naikkan FR-11 ke MVP.

### 5.1 Integrasi dengan ERP Google Apps Script + Google Sheets

Rekomendasi: sistem foto membaca data pasien langsung dari Google Sheets lewat Sheets API dengan service account berhak **Viewer** saja, bukan lewat endpoint GAS, dan tidak pernah menulis ke sheet di v1.

| Opsi | Cara kerja | Kelebihan | Kelemahan |
| --- | --- | --- | --- |
| A. Endpoint GAS Web App | ERP menambah fungsi `doGet`/`doPost` yang mengembalikan JSON pasien per No. RM | Tidak perlu akses langsung ke sheet; logika di ERP | Kena kuota dan latensi GAS; web app yang dipanggil server-ke-server biasanya harus dibuka "Anyone", sehingga keamanan bergantung pada token buatan sendiri \[medium confidence\] |
| B. Sheets API + service account (disarankan) | Backend sistem foto membaca range sheet pasien; service account hanya diberi akses Viewer | Read-only dijamin oleh izin Google; tidak membebani GAS; mudah di-cache | Struktur kolom sheet menjadi "kontrak"; perubahan kolom di ERP bisa memutus integrasi |
| C. Ekspor CSV berkala | ERP mengekspor daftar pasien terjadwal | Paling sederhana | Data bisa basi; pasien baru belum muncul sampai ekspor berikutnya |

**Kebutuhan integrasi (tambahan FR-03)**

- Kontrak kolom tertulis: nama sheet, kolom No. RM, nama, tanggal lahir, jenis kelamin, no. HP; perubahan kolom wajib dikoordinasikan.
- Validasi: No. RM harus unik di sheet; bila ada duplikat, sistem menolak dan meminta pilihan manual.
- Cache lokal minimal (identitas saja) + waktu sinkron; foto dan data klinis ortodonti **tidak** disimpan ke Google Sheets/Drive.
- Konfirmasi identitas di layar (nama + tanggal lahir) sebelum sesi foto dimulai.

**Jangan bangun sistem foto di atas GAS juga.** Web app GAS berjalan di dalam iframe sandbox milik Google, sehingga akses kamera, instalasi PWA, dan kontrol penyimpanan file jauh lebih terbatas dibanding web app biasa. \[medium confidence — berdasarkan pengetahuan umum HtmlService; perlu uji bila opsi ini tetap ingin dipertimbangkan\]

### 5.2 Identitas pasien: Dental Photo ID dan No. RM

Aplikasi berdiri sendiri, jadi kunci pasien adalah **Dental Photo ID (DPID)** milik aplikasi, bukan No. RM. No. RM hanya unik di dalam satu klinik/RME, dan satu pasien bisa punya No. RM berbeda di klinik berbeda.

- **Format usulan:** `DP-XXXX-XXXX`: 7 karakter acak base32 Crockford (tanpa I, L, O, U) + 1 karakter checksum. Kode acak, bukan urutan dan bukan dari nama/tanggal lahir, sehingga tidak membocorkan data dan tidak bisa ditebak. Checksum menangkap salah ketik saat DPID diketik ulang di RME.
- **Kunci internal:** UUID di database; DPID adalah kunci yang dibagikan ke luar (RME, report, nama file ekspor).
- **No. RM:** disimpan sebagai pasangan *sistem + nilai* (tabel `patient_external_id`), opsional, diisi manual atau sinkron (FR-03). Satu pasien bisa punya beberapa No. RM dari sistem berbeda.
- **Integrasi RME nanti (FR-24):** RME menyimpan DPID di data pasiennya; aplikasi menyimpan No. RM. Pencocokan selalu memakai pasangan ini, bukan nama.

### 5.3 Akun dokter, verifikasi, dan workspace

**Tingkat akun (MVP, belum ada pembayaran):**

| | Free | Premium |
| --- | --- | --- |
| Siapa | Dokter luar klinik yang mau mencoba | Dokter klinik (ditetapkan admin) dan dokter luar yang lolos verifikasi |
| Cara dapat | Daftar sendiri (email atau Google) | Admin developer menetapkan, atau dokter mengajukan dengan STR/SIP lalu disetujui admin developer |
| Batas pasien | 50 per workspace | Tanpa batas |
| Penyimpanan | 5 GB per workspace (±8 pasien sepanjang perawatan penuh, atau 50 pasien untuk beberapa sesi awal) | Tanpa batas |
| Asisten | Tidak bisa mengundang asisten | Bisa mengundang asisten |
| Fitur foto, galeri, report | Sama dengan Premium | Semua fitur |
| Biaya | Gratis selama masa uji 1–2 tahun | Gratis selama masa uji 1–2 tahun |

**Alur:**

1. Dokter mendaftar dengan email + kata sandi atau **Login dengan Google**, memverifikasi email, menyatakan dirinya dokter gigi berizin, dan menyetujui syarat layanan. Akun mulai sebagai **Free**.
2. Dokter mengisi profil (nama, nama praktik) dan menjadi pemilik **workspace**. Hanya akun Premium yang bisa mengundang asisten.
3. Untuk naik ke **Premium**, dokter mengunggah STR (dan SIP bila ada); **admin developer** meninjau dan mengubah status. Dokter klinik langsung ditetapkan Premium oleh admin.
4. Data pasien terisolasi per workspace. Berbagi pasien antar dokter/workspace (rujukan) **tidak** ada (keputusan 10 Okt).

**Catatan kritis.** Akun Free belum terverifikasi tetapi sudah menyimpan foto wajah dan rontgen pasien sungguhan. Batas 50 pasien membatasi dampak, bukan menghilangkan risiko. Mitigasi minimum: email terverifikasi, pernyataan dokter berizin + syarat layanan tercatat, admin bisa menangguhkan akun, dan semua akses tercatat di audit log. Karena dokter luar ikut sejak MVP, syarat layanan, perjanjian pemrosesan data, dan pendaftaran PSE (Bagian 9) menjadi syarat **sebelum akun Free dibuka**, bukan v1.1.

Verifikasi STR di MVP manual oleh admin platform. Pengecekan otomatis ke registri STR KKI dipertimbangkan nanti bila tersedia API resmi. \[low confidence soal ketersediaan API registri; perlu verifikasi\]

### 5.4 Layar utama

| Layar | Isi | Acuan |
| --- | --- | --- |
| Dashboard dokter | Header nama dokter, menu, tombol +; kolom Search; daftar pasien urut nama dengan pemisah huruf (A, B, ...) dan indeks huruf di sisi kanan; tiap baris: foto profil, nama (nama belakang tebal), tanggal lahir | FR-31, contoh layar dari dokter |
| Sheet pasien | Foto profil, nama, tanggal lahir, email, no. HP, DPID, No. RM; tombol Ubah; bar bawah: Galeri, tombol + (mulai sesi foto), Arsip | FR-32 |
| Galeri | Foto pasien dikelompokkan per kategori (wajah, kiri, depan, kanan, oklusal atas/bawah, rontgen) dengan filter tahap/tanggal | FR-15 |
| Arsip | Daftar sesi per tanggal kontrol: tanggal, tahap (Awal/Insersi/Kontrol ke-n/Lepas), keterangan, jumlah foto; ketuk untuk detail | FR-33 |
| Tambah/ubah pasien | Nama, tanggal lahir, email, no. HP, No. RM (opsional), jenis kelamin (opsional) | FR-02 |

## 6. Modul capture dengan guide positioning

Modul capture adalah wizard berurutan yang mengikuti halaman template case report: 3 foto wajah → 5 foto intraoral → 2 rontgen (upload) → 5 foto setelah insersi. Urutan ini juga mengurangi bolak-balik pasang-lepas retraktor (semua ekstraoral dulu, baru intraoral).

### 6.1 Slot foto per tahap

| Kode | Slot (label UI) | Halaman template | Awal | Insersi | Kontrol | Lepas |
| --- | --- | --- | --- | --- | --- | --- |
| EO-01 | Wajah depan, rest position | Profil Wajah | Wajib | — | Opsional | Wajib |
| EO-02 | Wajah depan, senyum | Profil Wajah | Wajib | — | Opsional | Wajib |
| EO-03 | Profil kanan pasien (standar ABO) | Profil Wajah | Wajib | — | Opsional | Wajib |
| IO-01 | Intraoral kiri (bukal kiri) | Profil Wajah | Wajib | Wajib | Wajib | Wajib |
| IO-02 | Intraoral depan (frontal) | Profil Wajah | Wajib | Wajib | Wajib | Wajib |
| IO-03 | Intraoral kanan (bukal kanan) | Profil Wajah | Wajib | Wajib | Wajib | Wajib |
| IO-04 | Oklusal atas | Profil Wajah | Wajib | Wajib | Wajib | Wajib |
| IO-05 | Oklusal bawah | Profil Wajah | Wajib | Wajib | Wajib | Wajib |
| RX-01 | Rontgen panoramik (upload) | Rontgen | Wajib | — | — | Opsional |
| RX-02 | Rontgen sefalometri (upload) | Rontgen | Wajib | — | — | Opsional |

Kolom Wajib/Opsional adalah usulan default dan harus dikonfirmasi dokter; template tahap disimpan sebagai konfigurasi, bukan kode. "Foto Setelah Insersi" di template = slot IO-01 s.d. IO-05 pada sesi bertahap Insersi.

### 6.2 Spesifikasi overlay guide per slot

| Kode | Orientasi | Elemen overlay | Checklist pra-capture | Pasca-capture |
| --- | --- | --- | --- | --- |
| EO-01 | Portrait | Siluet kepala-bahu; garis interpupil horizontal; garis tengah wajah vertikal; batas atas hairline dan bawah dagu (skala \~1/4) | Latar putih, kacamata dilepas, telinga terlihat, rambut tidak menutupi wajah, bibir rileks | Cek otomatis kemiringan kepala (v1.1) |
| EO-02 | Portrait | Sama dengan EO-01 | Senyum sosial alami | Sama dengan EO-01 |
| EO-03 | Portrait | Siluet profil menghadap ke kanan frame; garis Frankfort horizontal (tragus–bawah mata); target titik tengah frame di depan tragus | Latar putih, telinga terlihat, bahu tegak lurus kamera | — |
| IO-01 / IO-03 | Landscape | Garis bidang oklusal horizontal di tengah frame; garis vertikal di distal kaninus; batas molar pertama | Dua retraktor, gigi oklusi maksimal, bersih dari saliva | Flip bila diambil via cermin bukal |
| IO-02 | Landscape | Garis bidang oklusal horizontal; garis midline vertikal di tengah frame; outline lengkung gigi | Dua retraktor, oklusi maksimal | — |
| IO-04 | Landscape | Outline lengkung U rahang atas; garis midline palatal; zona aman tepi cermin | Cermin oklusal hangat (anti-embun), mulut terbuka maksimal | Auto-flip vertikal (foto cermin) |
| IO-05 | Landscape | Outline lengkung U rahang bawah; garis midline; zona lidah | Lidah ditarik ke belakang, cermin oklusal | Auto-flip vertikal (foto cermin) |

### 6.3 Fitur "high-tech" di layar capture

1. **Overlay SVG di atas live preview** (`getUserMedia` + `<video>` + layer SVG), dapat diatur opasitasnya.
2. **Indikator kemiringan perangkat** dari sensor orientasi HP: garis "waterpass" berubah hijau saat HP tegak/rata dalam toleransi ±2°. Di iOS, akses sensor butuh izin pengguna. \[medium confidence — perlu verifikasi live di Safari versi terbaru\]
3. **Ghost overlay** foto kategori sama dari sesi sebelumnya (FR-11).
4. **Deteksi wajah on-device untuk ekstraoral** (v1.1): landmark wajah di browser (mis. MediaPipe Face Landmarker) untuk memeriksa roll/yaw kepala dan garis interpupil, lalu memberi status "posisi OK". Untuk intraoral, deteksi otomatis tidak dimasukkan v1 karena butuh model khusus dan dataset gigi. \[low confidence soal akurasi di HP kelas menengah; perlu proof-of-concept\]
5. **Mode "Kamera bawaan (resolusi penuh)"** sebagai alternatif: membuka kamera native lewat `<input type="file" capture>`. Hasil lebih tajam, tetapi **tanpa overlay**. Tradeoff ini harus ditampilkan ke pengguna, bukan disembunyikan.
6. **Grid sesi**: setelah tiap capture, layar menampilkan 11 slot (8 wajib + 3 tambahan, sesuai `slots.v2.json`) sehingga pengguna langsung melihat slot yang masih kosong.

### 6.4 Alur capture (wizard)

1. Pilih/cari pasien → pilih kasus → buat sesi (tahap otomatis menyarankan Kontrol ke-n berikutnya).
2. Sistem memuat template slot sesuai tahap.
3. Untuk tiap slot: tampil instruksi singkat + gambar contoh → overlay aktif → capture atau upload.
4. Review cepat: terima, retake, atau edit (crop/rotate/flip).
5. Lanjut ke slot berikutnya; slot boleh dilewati (sesi jadi draft).
6. Rontgen: tombol upload saja.
7. Ringkasan sesi: grid semua slot → simpan → unggah (atau antre bila offline).

### 6.5 SOP non-software yang wajib menyertai

Overlay tidak menggantikan setting fisik yang konsisten. Tanpa poin di bawah, foto kontrol tetap tidak sebanding:

- Titik berdiri pasien dan fotografer ditandai di lantai; jarak kamera tetap untuk ekstraoral.
- Latar putih permanen di dinding foto (template klinik mewajibkannya).
- Satu perangkat foto khusus klinik, bukan HP pribadi staf bergantian; zoom optik yang sama tiap sesi.
- Pencahayaan tetap; untuk intraoral pertimbangkan lampu tambahan untuk HP (lihat Smile Lite MDP di Bagian 2).
- Set retraktor dan cermin oklusal standar.

## 7. Model data dan metadata

Setiap foto terikat ke rantai Pasien → Kasus → Sesi → Slot, sehingga tag pasien\_id, tanggal, tahap, dan kategori tidak pernah diketik manual; semuanya diturunkan dari sesi dan slot tempat foto diambil.

| Entitas | Field utama | Catatan |
| --- | --- | --- |
| user | id, email, nama, auth\_provider (password/google), email\_verified\_at, status | Akun dokter, asisten, admin platform |
| dentist\_verification | id, user\_id, no\_str, file\_str\_url, file\_sip\_url, status (menunggu/disetujui/ditolak), alasan, ditinjau\_oleh, ditinjau\_at | Pengajuan upgrade ke Premium |
| workspace | id, nama\_praktik, pemilik\_user\_id, plan (free/premium), batas\_pasien (50/null), batas\_penyimpanan\_bytes (5 GB/null), terpakai\_bytes, status (aktif/ditangguhkan), plan\_diubah\_oleh, plan\_diubah\_at | Unit isolasi data (pengganti clinic\_id); batas pasien dicek di server |
| terms\_acceptance | user\_id, versi\_syarat, pernyataan\_dokter (bool), disetujui\_at, ip/perangkat | Bukti persetujuan syarat layanan |
| workspace\_member | workspace\_id, user\_id, peran (dokter/asisten), diundang\_oleh, status | Peran per workspace |
| patient | id (UUID), dpid (unik global, tetap), workspace\_id, nama, tgl\_lahir, email, no\_hp, jenis\_kelamin (opsional), avatar\_photo\_id | DPID tidak pernah berubah |
| patient\_external\_id | patient\_id, sistem (mis. rme\_klinik\_x), nilai (No. RM), source (manual/sync), synced\_at | Unik per (workspace, sistem, nilai) |
| ortho\_case | id, patient\_id, tipe\_behel, tgl\_pemasangan, tgl\_lepas, dokter\_id, status | Satu pasien bisa banyak kasus |
| session | id, case\_id, tahap (awal/insersi/kontrol/lepas/retensi), nomor\_kontrol, tgl\_sesi, keterangan, status (draft/lengkap), dibuat\_oleh | Tahap menentukan template slot; keterangan tampil di Arsip |
| slot\_template | id, tahap, kode\_slot, label, wajib, urutan, overlay\_id | Konfigurasi, bisa diubah admin |
| photo | id, session\_id, kode\_slot, kategori, sumber (kamera/upload), file\_asli\_url, file\_edit\_url, edit\_params (JSON), lebar, tinggi, perangkat, diambil\_oleh, diambil\_at, diganti\_oleh\_photo\_id, hash\_sha256 | Asli tidak pernah ditimpa |
| consent | id, patient\_id, jenis (dokumentasi klinis/edukasi/publikasi), tgl, berlaku\_sampai, file\_bukti\_url | Mengunci fitur anonimisasi dan share |
| report | id, case\_id, template, before\_session\_id, after\_session\_id, foto\_terpilih (JSON), pdf\_url, dibuat\_oleh, dibuat\_at | PDF disimpan sebagai snapshot |
| audit\_log | id, user\_id, aksi, entitas, entitas\_id, waktu, ip/perangkat | Append-only |

**Kategori foto (enum tetap):** wajah\_depan\_rest, wajah\_depan\_senyum, wajah\_profil\_kanan, io\_kiri, io\_depan, io\_kanan, io\_oklusal\_atas, io\_oklusal\_bawah, io\_anterior\_atas, io\_anterior\_bawah, rx\_panoramik, rx\_sefalometri, lainnya.

**Metadata EXIF.** Sistem membaca EXIF dari file upload (tanggal ambil, perangkat) untuk peringatan bila tanggal foto jauh dari tanggal sesi, tetapi **menghapus EXIF lokasi GPS** sebelum menyimpan. Foto hasil capture browser biasanya tidak membawa EXIF kamera yang lengkap, sehingga parameter perangkat dicatat oleh aplikasi. \[medium confidence\]

**Basis data dan penyimpanan (rencana 13 Okt 2026, belum final).** Basis data MySQL di Hostinger; file foto di cloud hosting, bukan di server database. MySQL tidak punya row-level security, jadi isolasi workspace (Bagian 10.1) harus ditegakkan di lapisan aplikasi: setiap query wajib difilter `workspace_id`, plus uji otomatis akses lintas workspace.

**Penyimpanan file.** Object storage (S3-compatible) dengan URL bertanda tangan berumur pendek; thumbnail dibuat di server untuk galeri agar cepat di HP.

## 8. Kebutuhan non-fungsional dan keputusan PWA

PWA adalah pilihan yang masuk akal untuk v1, tetapi kualitas kamera **tidak setara** antara Android dan iPhone di browser. Asumsi "getUserMedia sudah cukup untuk foto kualitas tinggi" hanya kuat di Android Chrome; di iPhone asumsi itu lemah dan harus diuji sebelum komitmen.

### 8.0 Keputusan ulang: semua perangkat adalah iPhone

Dengan perangkat hanya iPhone 13 Pro Max dan iPhone 17 Pro Max, PWA murni menjadi pilihan **paling berisiko** untuk modul capture, bukan paling aman. Alasan "staf campur Android/iPhone" gugur, sementara kelemahan kamera web di iOS tetap ada.

- Di iOS, semua browser memakai mesin WebKit, sehingga Chrome di iPhone tidak memberi kemampuan kamera lebih dari Safari. \[high confidence — kebijakan Apple yang sudah lama berlaku; perlu cek live untuk perubahan regulasi di luar Indonesia, tetapi tidak relevan untuk perangkat di Indonesia\]
- Tanpa `takePhoto()`, foto dari web adalah frame video, bukan foto resolusi penuh 48 MP dari kamera iPhone Pro. \[medium confidence — sumber W3C di 8.1 bertanggal Februari 2023\]
- Kedua iPhone punya kamera telefoto dan mode makro di aplikasi Kamera bawaan. Apakah halaman web bisa memilih lensa telefoto atau makro secara andal lewat `getUserMedia` belum pasti. \[low confidence — harus diuji di PoC\]

**Tiga opsi arsitektur capture**

| Opsi | Cara kerja | Kualitas foto | Overlay guide | Biaya/kompleksitas |
| --- | --- | --- | --- | --- |
| A. PWA murni | `getUserMedia` + overlay SVG | Frame video; fokus otomatis saja | Ya | Rendah |
| B. PWA + kamera bawaan | Tombol membuka kamera native iOS; foto masuk ke slot | Penuh (sesuai kamera iPhone) | Tidak saat memotret; ghost foto sebelumnya hanya ditampilkan sebelum membuka kamera | Rendah |
| C. Hybrid (mis. Capacitor) | Kode web yang sama dibungkus app iOS; plugin kamera native (AVFoundation) dengan overlay | Penuh; bisa pilih lensa, tap-to-focus, kunci eksposur | Ya, termasuk ghost overlay | Sedang: butuh developer iOS dan akun Apple Developer |

**Rekomendasi.** Jalankan PoC opsi A vs B pada kedua iPhone untuk 8 foto standar, dinilai blind oleh 2 dokter. Jika A layak untuk ekstraoral tetapi tidak untuk intraoral, pakai kombinasi: A untuk wajah, B untuk intraoral. Jika konsistensi intraoral tetap buruk, pindah ke C. Karena perangkat hanya 2 iPhone milik klinik, distribusi app internal tanpa rilis publik di App Store dimungkinkan (mis. distribusi ad hoc untuk perangkat terdaftar). \[medium confidence — opsi distribusi Apple dan biayanya perlu verifikasi live\]

**Hasil (G0, 13 Okt 2026):** opsi A dipilih untuk semua slot; lihat keputusan G0 di Bagian 11.2.

Perubahan turunan: NFR-01 menjadi "Safari iOS versi 2 rilis terakhir" (Android tidak lagi wajib diuji); NFR-CAM-01 menjadi gerbang keputusan A/B/C, bukan sekadar uji kualitas.

### 8.1 Perbedaan kemampuan kamera web per platform

| Kemampuan | Android (Chrome) | iPhone (Safari/semua browser iOS) |
| --- | --- | --- |
| Ambil foto resolusi penuh lewat `ImageCapture.takePhoto()` | Tersedia | Tidak tersedia; foto = frame video yang ditangkap ke canvas |
| Kontrol fokus, zoom, white balance, ISO via constraint | focusMode, zoom, whiteBalanceMode, iso tersedia | Terbatas; tidak ada kontrol fokus manual |
| Flash/fill light saat foto | fillLightMode tersedia | Tidak tersedia lewat web |
| Kamera native resolusi penuh via `<input capture>` | Ya, tanpa overlay | Ya, tanpa overlay |

Sumber: [status implementasi W3C Image Capture](https://github.com/w3c/mediacapture-image/blob/main/implementation-status.md) mencantumkan iOS sebagai platform yang tidak didukung untuk implementasi Chrome, dan bagian Safari kosong. \[medium confidence — dokumen itu terakhir diperbarui Februari 2023; dukungan Safari terbaru **perlu verifikasi live** dengan uji perangkat nyata\]

**Konsekuensi untuk requirement**

- **NFR-CAM-01**: Lakukan proof-of-concept 1–2 minggu sebelum build penuh: ambil 8 foto standar di 3 perangkat (1 iPhone, 1 Android flagship, 1 Android menengah) via PWA vs kamera native, lalu dokter menilai kelayakan klinis secara blind.
- **NFR-CAM-02**: Resolusi minimum foto tersimpan 1920 px sisi panjang untuk intraoral; bila stream kamera di bawah itu, tampilkan peringatan dan sarankan mode kamera native/upload. \[low confidence soal angka ini — usulan awal, perlu disepakati dokter\]
- **NFR-CAM-03**: Bila PoC gagal di iPhone, jalur aman adalah: iPhone memakai kamera native + upload ke slot, sementara overlay hanya dipakai di Android. Native iOS app baru dipertimbangkan bila hasil ini tidak dapat diterima.

### 8.2 Kebutuhan non-fungsional lain

| ID | Kebutuhan | Target |
| --- | --- | --- |
| NFR-01 | Platform | PWA installable (manifest + service worker); Safari iOS versi 2 rilis terakhir (semua perangkat iPhone); app hybrid bila opsi C di 8.0 dipilih |
| NFR-02 | Kinerja galeri | Thumbnail tampil < 2 detik di 4G untuk 50 foto |
| NFR-03 | Unggah | Unggah dilanjutkan otomatis setelah koneksi putus; kompresi di klien opsional, file asli tetap tersimpan |
| NFR-04 | Offline | Capture dan simpan sesi tanpa internet; sinkron otomatis (IndexedDB + background sync bila didukung) |
| NFR-05 | Penyimpanan lokal | Foto dihapus dari perangkat setelah terunggah, agar data pasien tidak menumpuk di HP staf |
| NFR-06 | Ketersediaan | 99,5% per bulan pada jam praktik |
| NFR-07 | Backup | Backup harian, retensi sesuai kebijakan rekam medis (lihat Bagian 9) |
| NFR-08 | Report | PDF A4, gambar ≥ 150 dpi saat cetak; generate < 15 detik |
| NFR-09 | Usability | Satu sesi lengkap 8 foto wajib (3 slot tambahan opsional) bisa diselesaikan asisten terlatih dalam ≤ 5 menit (di luar waktu persiapan pasien) |

**Risiko PWA di iOS yang perlu dicatat.** Penyimpanan lokal PWA di iOS bisa dibersihkan sistem bila app jarang dipakai, sehingga antrian offline (FR-12) berisiko kehilangan foto yang belum terunggah. \[medium confidence — perilaku ini berubah antar versi iOS; perlu verifikasi live\] Mitigasi: indikator unggah yang mencolok dan larangan menutup sesi sebelum semua foto terunggah.

## 9. Keamanan, privasi, dan regulasi

Foto klinis dan rontgen pasien adalah bagian rekam medis, sehingga sistem ini harus diperlakukan sebagai komponen RME, bukan sekadar galeri foto. Ini adalah syarat go-live, bukan fitur opsional.

| Aturan | Isi yang relevan | Kebutuhan sistem |
| --- | --- | --- |
| [Permenkes 24/2022 tentang Rekam Medis](https://pasal.id/peraturan/permen/permenkes-no-24-tahun-2022) | Fasyankes wajib menyelenggarakan RME; penyimpanan harus menjamin keamanan, keutuhan, kerahasiaan, dan ketersediaan data | Enkripsi, kontrol akses, audit log, backup, hash file (keutuhan) |
| Permenkes 24/2022 Pasal 39 | [Retensi RME paling singkat 25 tahun sejak kunjungan terakhir pasien](https://medminutes.io/blog/retensi-pemusnahan-rekam-medis-elektronik-permenkes-24-2022/) | Tidak ada hapus permanen oleh pengguna; arsip dingin (cold storage) untuk biaya; estimasi kapasitas jangka panjang |
| Aplikasi mandiri untuk banyak dokter | Karena akun Free dibuka untuk dokter luar sejak MVP, platform kemungkinan menjadi Penyelenggara Sistem Elektronik lingkup privat yang wajib terdaftar; dokter/praktik = pengendali data pasien, platform = prosesor \[medium confidence; this needs verification oleh konsultan hukum\] | Pendaftaran PSE; syarat layanan + perjanjian pemrosesan data dengan tiap dokter; ekspor data pasien bila dokter berhenti berlangganan |
| UU 27/2022 tentang Pelindungan Data Pribadi | Data kesehatan dan data biometrik termasuk data pribadi yang bersifat spesifik \[medium confidence — dari ingatan, belum dibuka sumbernya di riset ini; this needs verification oleh konsultan hukum\] | Dasar pemrosesan + persetujuan tercatat; hak akses dan koreksi pasien; prosedur notifikasi kebocoran |

**Kebutuhan keamanan (MVP)**

- HTTPS wajib; enkripsi at-rest di object storage dan database.
- Akses file hanya via URL bertanda tangan yang kedaluwarsa (mis. 5–15 menit); tidak ada URL publik permanen.
- Data pasien di perangkat (IndexedDB) dihapus setelah terunggah; PWA meminta PIN/biometrik perangkat sebelum membuka galeri bila memungkinkan.
- Watermark opsional (nama klinik + tanggal) pada report yang dibagikan.
- Tombol share ke WhatsApp hanya membagikan PDF yang sudah lolos cek consent.

**Consent (persetujuan) pasien — tiga level terpisah**

1. Dokumentasi klinis (wajib untuk perawatan).
2. Edukasi internal/ilmiah (presentasi, case report session).
3. Publikasi/marketing (media sosial, website) — terpisah dan bisa ditarik kembali.

Template case report klinik berlabel "case report session" dan contoh memperlihatkan wajah penuh. Jika report dipakai di luar perawatan pasien, consent level 2 atau 3 harus tercatat sebelum export. Untuk pasien di bawah 18 tahun, consent dari orang tua/wali.

**Lokasi data.** Apakah server wajib berada di Indonesia untuk data rekam medis perlu dicek terhadap aturan PSE dan kebijakan Kemenkes terbaru. \[low confidence — aturan teknis ini sering berubah; perlu verifikasi live\] Pilihan aman: gunakan region cloud Jakarta.

## 10. Risiko, pertanyaan terbuka, dan urutan rilis

Risiko terbesar bukan di pengembangan software, melainkan di dua hal: kualitas kamera browser di iPhone, dan disiplin SOP foto di klinik. Keduanya harus diuji sebelum build penuh.

### 10.1 Risiko utama

| Risiko | Dampak | Kemungkinan | Mitigasi |
| --- | --- | --- | --- |
| Foto intraoral via PWA di iPhone tidak layak klinis | Tinggi: fitur inti gagal untuk sebagian staf | Sedang | PoC sebelum build (NFR-CAM-01); jalur kamera native + upload |
| Staf tidak konsisten (jarak, cahaya, latar) meski ada overlay | Tinggi: before/after tidak valid | Tinggi | SOP fisik (Bagian 6.5), perangkat foto khusus klinik, pelatihan |
| API RME tidak tersedia atau tidak terdokumentasi | Sedang: input manual ganda | Sedang | Input manual sebagai default; adapter terpisah |
| Foto hilang di antrian offline iOS | Tinggi: data rekam medis hilang | Rendah–sedang | Indikator unggah, blokir tutup sesi sebelum terunggah |
| Foto pasien bocor (HP staf, link share) | Sangat tinggi: hukum dan reputasi | Sedang | URL kedaluwarsa, hapus lokal, consent gate, audit log |
| Akun Free palsu (bukan dokter gigi) menyimpan foto wajah/rontgen pasien | Tinggi: hukum dan reputasi | Sedang (pendaftaran terbuka tanpa verifikasi) | Batas 50 pasien; email terverifikasi; pernyataan dokter berizin + syarat layanan tercatat; admin bisa menangguhkan; audit log. Opsi lebih ketat: akun Free hanya pasien demo sampai diverifikasi |
| Layanan gratis (Free dan Premium) selama masa uji 1–2 tahun tetapi retensi foto 25 tahun | Sedang–tinggi: biaya penyimpanan ditanggung platform tanpa pemasukan | Tinggi bila banyak dokter luar bergabung | Batas 50 pasien dan 5 GB untuk Free; aturan akhir masa uji tertulis di syarat layanan; ukur biaya per pasien per tahun di MVP; kompresi turunan + cold storage; putuskan harga Premium sebelum banyak dokter luar |
| Akun Free ditinggalkan dengan data pasien | Sedang: data tetap wajib disimpan, tanpa pemilik aktif | Sedang | Kebijakan akun tidak aktif di syarat layanan; ekspor data untuk dokter; kontak admin |
| Kebocoran data antar workspace | Sangat tinggi | Rendah–sedang | Isolasi per workspace: filter `workspace_id` wajib di lapisan aplikasi (MySQL tidak punya row-level security), uji otomatis akses lintas workspace |
| Pasien duplikat (input manual tanpa No. RM) | Sedang: foto terpecah di dua pasien | Sedang | Peringatan nama + tanggal lahir sama; fitur gabung pasien (v1.1) |
| Biaya penyimpanan membengkak (retensi 25 tahun) | Sedang | Tinggi dalam jangka panjang | Kompresi turunan, cold storage untuk kasus selesai |
| RME berbasis GAS + Google Sheets: pengguna dengan akses Editor bisa mengubah/menghapus baris langsung di sheet tanpa lewat ERP; tidak ada constraint unik No. RM; kepatuhan audit/keutuhan Permenkes 24/2022 bergantung pada edisi Google Workspace dan pengaturan akses | Tinggi: risiko kepatuhan dan foto tertaut ke pasien yang salah | Sedang–tinggi | Integrasi opsi B di 5.1 (read-only); konfirmasi identitas sebelum sesi; batasi akses Editor sheet hanya ke admin; cek apakah klinik memakai Google Workspace berbayar (log audit Drive, kebijakan berbagi) dan lokasi data \[this needs verification\]; evaluasi migrasi RME ke database sebagai proyek terpisah |

### 10.2 Pertanyaan terbuka untuk klinik

- [x] Aplikasi RME apa yang dipakai, dan apakah vendor menyediakan API? **Jawaban:** RME dikembangkan sendiri: ERP berbasis Google Apps Script dengan database Google Sheets (integrasi: Bagian 5.1).
- [x] Apakah foto ekstraoral diambil ulang setiap kontrol? **Jawaban:** diambil lagi, tetapi opsional.
- [x] Arah profil "Hadap Kiri"? **Jawaban:** gunakan standar ABO (wajah menghadap ke kanan frame).
- [x] Perangkat foto? **Jawaban:** iPhone 13 Pro Max dan iPhone 17 Pro Max.
- [x] Berapa dokter dan cabang? **Jawaban:** 2 dokter (diasumsikan 1 klinik).
- [x] Report dipakai untuk media sosial? **Jawaban:** ya, consent publikasi dibutuhkan sejak MVP.
- [x] Template report lain selain case report? **Jawaban:** ya, dibutuhkan.

**Dampak jawaban terhadap requirement**

1. **Semua perangkat iPhone mengubah keputusan teknologi.** Alasan utama PWA (staf campur Android/iPhone) tidak berlaku lagi, dan iPhone adalah platform terlemah untuk kamera berbasis web. Lihat Bagian 8.0.
2. **RME berbasis spreadsheet bukan API.** FR-03 diubah menjadi lookup ke spreadsheet; risiko kepatuhan ditambahkan ke 10.1.
3. **Consent publikasi dan anonimisasi (FR-22) naik ke MVP**; template report progres pasien ditambahkan sebagai FR-26 (MVP).
4. ~~**Multi-klinik tidak diperlukan.**~~ **Diganti revisi 10 Okt:** aplikasi dipakai dokter dari mana saja, jadi isolasi data per workspace (multi-tenant) wajib sejak MVP (Bagian 5.3).
5. **Label EO-03 diseragamkan** menjadi "Profil kanan pasien (standar ABO)".

**Pertanyaan terbuka dari revisi 10 Okt**

- [x] Email dan no. HP pasien dipakai untuk apa? **Jawaban:** tidak dipakai untuk mengirim apa pun; hanya data kontak/identitas.
- [x] Apakah satu pasien bisa dibagikan ke dokter lain (rujukan)? **Jawaban:** tidak.
- [x] Siapa yang memverifikasi STR? **Jawaban:** tidak wajib untuk mulai memakai; verifikasi STR oleh admin hanya untuk upgrade ke Premium (FR-28).
- [x] Gratis atau berlangganan? **Jawaban:** gratis untuk sementara, dengan tingkat **Free** (maks. 50 pasien) untuk dokter luar yang mencoba dan **Premium** (tanpa batas) setelah diverifikasi admin. Belum ada pembayaran. Risiko biaya dicatat di 10.1.
- [x] Apakah akun Free boleh mengundang asisten? **Jawaban:** tidak.
- [x] Perlu batas penyimpanan selain 50 pasien? **Jawaban:** ya, 5 GB untuk akun Free.
- [x] Siapa yang meninjau pengajuan Premium? **Jawaban:** admin developer. Program gratis (Free dan Premium) berjalan sebagai masa uji 1–2 tahun.
- [ ] Target waktu tinjau pengajuan Premium (mis. 3 hari kerja)?
- [ ] Setelah masa uji 1–2 tahun selesai: apa yang terjadi pada akun Free dan Premium yang tidak membayar? Foto tetap wajib disimpan 25 tahun, jadi pilihan realistis: akun menjadi hanya baca + ekspor, bukan dihapus. Harus tertulis di syarat layanan sejak awal.
- [x] Jenis kelamin pasien? **Jawaban:** tetap opsional.

### 10.3 Urutan rilis yang disarankan

1. **Fase 0 — PoC kamera (1–2 minggu):** uji 8 foto di iPhone dan Android via PWA vs kamera native; keputusan go/no-go untuk capture langsung di iOS.
2. **MVP:** registrasi + login Google, akun Free (50 pasien) / Premium (verifikasi admin), workspace, syarat layanan, dashboard pasien, sheet pasien dengan Dental Photo ID (No. RM opsional), Galeri dan Arsip, kasus + sesi, capture dengan overlay generik, upload, rontgen upload, galeri per kategori, case report PDF, report progres pasien (FR-26), peran dan audit log, consent tiga level termasuk publikasi, anonimisasi report untuk media sosial.
3. **v1.1:** pembayaran Premium (bila diputuskan), ghost overlay, timeline, slider/toggle compare, antrian offline, deteksi wajah ekstraoral, lookup otomatis ke spreadsheet RME.
4. **v2:** kirim balik ke RME, deteksi otomatis kualitas intraoral, DICOM, analitik klinik.

Estimasi durasi tiap fase tidak dicantumkan karena bergantung pada ukuran tim dan hasil PoC; angka apa pun sekarang akan menjadi tebakan.

## 11. Rencana implementasi

Urutan kerja: PoC kamera dulu, karena hasilnya menentukan arsitektur capture (Bagian 8.0) dan isi repo. Bagian ini merinci dan menggantikan urutan rilis di 10.3.

### 11.1 Urutan rilis

&#91;embedded content: urutan rilis · 5 fase, 4 gate\]

Setiap fase hanya dimulai setelah gate sebelumnya lolos. Durasi hanya dicantumkan untuk Fase 0; fase lain bergantung pada ukuran tim dan hasil G0.

| Gate | Kriteria lolos | Bila gagal |
| --- | --- | --- |
| G0 | Matriks slot × opsi disetujui 2 dokter. Spike Sheets API dikeluarkan dari G0 (rencana basis data MySQL, Bagian 7) | Ulang uji dengan lensa/jarak berbeda, atau langsung putuskan opsi C |
| G1 | Login + peran, audit log, enkripsi, URL bertanda tangan berfungsi di staging | Perbaiki sebelum data pasien nyata masuk |
| G2 | Foto alpha dari pasien nyata dinilai layak oleh kedua dokter; tidak ada foto hilang | Kembali ke opsi capture lain untuk slot yang gagal |
| G3 | Checklist go-live: consent, restore backup teruji, checklist uji manual iPhone lolos | Tunda v1.0; tetap jalan paralel dengan proses lama |

### 11.2 Fase 0 — PoC kamera (dikerjakan pertama)

Tujuan Fase 0 hanya satu: memutuskan opsi capture A, B, atau C **per slot** berdasarkan data dari dua iPhone klinik, sebelum kode produk ditulis. Durasi usulan 1–2 minggu; hasilnya berupa matriks keputusan, bukan fitur.

**Lingkup PoC (sengaja minimal)**

1. Satu halaman web statis di HTTPS (kamera web hanya jalan di secure context), tanpa backend produk.
2. Mode A: `getUserMedia` + overlay SVG untuk 8 slot; tombol capture menyimpan frame ke canvas.
3. Mode B: tombol `<input type="file" accept="image/*" capture="environment">` ke kamera bawaan iOS.
4. Panel diagnostik: daftar kamera dari `enumerateDevices()`, hasil `track.getCapabilities()` dan `getSettings()` (resolusi, zoom, focusMode, torch).
5. Setiap foto diunduh atau diunggah ke folder uji, dengan nama file berisi perangkat, mode, slot, dan nomor ulangan.
6. Spike paralel kecil: baca 1 baris pasien dari Google Sheets via service account Viewer (Bagian 5.1).

**Protokol uji**

- Subjek: relawan staf dengan consent tertulis, bukan pasien, agar PoC tidak memicu urusan data pasien.
- Matriks: 2 iPhone × 2 mode (A, B) × 8 slot × 3 ulangan = 96 foto per relawan.
- Uji A di dua kondisi: tab Safari biasa dan PWA "Add to Home Screen", karena perilaku izin kamera bisa berbeda. \[medium confidence; justru ini yang diuji\]
- Pencahayaan, latar putih, retraktor, dan cermin sama untuk semua foto.
- Satu asisten yang sama memotret; waktu per set 8 foto dicatat.

**Metrik yang dicatat**

| Metrik | Cara ukur | Untuk keputusan |
| --- | --- | --- |
| Resolusi tersimpan (px sisi panjang) | Dari file hasil | Mode A layak bila ≥ 1920 px (usulan, Bagian 8) |
| Lensa yang bisa dipilih web | Label `enumerateDevices()`; coba pilih telefoto | Apakah intraoral via web bisa memakai lensa yang tepat |
| Kontrol kamera tersedia | `getCapabilities()`: zoom, focusMode, torch | Apakah fokus/zoom bisa dikunci untuk konsistensi |
| Kualitas klinis | 2 dokter menilai skala 1–5 secara blind (mode dan perangkat disembunyikan) | Kriteria utama |
| Konsistensi antar ulangan | Selisih sudut bidang oklusal dan posisi midline antar 3 ulangan | Seberapa besar overlay membantu |
| Waktu per set | Stopwatch | NFR-09 (≤ 5 menit) |
| Stabilitas | Crash, prompt izin berulang, panas perangkat | Kelayakan operasional |

**Kriteria keputusan per slot (usulan, harus disetujui dokter sebelum uji dimulai)**

- **A dipilih** bila skor blind rata-rata ≥ 4 dan selisihnya dengan B ≤ 0,5 poin, serta resolusi lolos.
- **B dipilih** bila A gagal tetapi B lolos skor dan konsistensi dapat diterima dokter.
- **C dipertimbangkan** bila A gagal di slot intraoral dan konsistensi B dinilai tidak cukup untuk before/after.

Kriteria ditetapkan sebelum melihat hasil agar keputusan tidak dibentuk oleh preferensi setelah melihat foto. Kemungkinan hasil yang realistis: A untuk 3 foto wajah, B atau C untuk intraoral. \[low confidence — tebakan berbasis keterbatasan yang diketahui, bukan data\]

**Keluaran Fase 0:** matriks slot × opsi, laporan singkat metrik, dan keputusan tertulis G0. Kode PoC disimpan di repo (folder `poc/`) sebagai referensi, bukan dipromosikan menjadi kode produk.

**Keputusan G0 (13 Okt 2026).** Capture memakai **opsi A (PWA murni: `getUserMedia` + overlay SVG) untuk semua slot**, termasuk intraoral dan oklusal. Opsi B dan C tidak dipakai di deployment awal; `apps/ios` tidak dibuat.

| Slot | Label | Wajib | Opsi |
| --- | --- | --- | --- |
| EO-01 | Wajah depan, rest | Ya | A |
| EO-02 | Wajah depan, senyum | Ya | A |
| EO-03 | Profil kanan (standar ABO) | Ya | A |
| IO-01 | Intraoral kiri | Ya | A |
| IO-02 | Intraoral depan | Ya | A |
| IO-03 | Intraoral kanan | Ya | A |
| IO-04 | Oklusal atas | Ya | A |
| IO-05 | Oklusal bawah | Ya | A |
| IO-06 | Anterior atas | Tidak | A |
| IO-07 | Anterior bawah | Tidak | A |
| FREE | Bebas | Tidak | A |

Daftar slot dan garis bantu mengikuti PoC final (`poc/camera/slots.v2.json`, 11 slot: 8 wajib + 3 tambahan), bukan 8 slot rencana awal. Perilaku kamera PoC v2.5 menjadi acuan produk: default lensa utama (zoom 1×–5× = crop lensa yang sama sehingga fokus stabil), multi-lensa iOS dan lensa manual tetap bisa dipilih di Pengaturan, zoom diingat per slot, dan Kunci fokus bila browser mendukung. Folder `poc/` dibekukan dengan tag `poc-v0`. Foto relawan sudah dihapus dari HP dan hosting. Spike Sheets API tidak dijalankan dan dikeluarkan dari G0 karena rencana basis data beralih ke MySQL di Hostinger; sinkron RME (FR-03) tetap opsional dan diuji saat fitur itu dibangun.

### 11.2.1 Langkah teknis Fase 0

Fase 0 **tidak membutuhkan basis data dan tidak boleh live di production**. Yang dibutuhkan hanya halaman web statis yang otomatis ter-deploy ke URL HTTPS terbatas setiap kali kode di-push. Itu adalah CI/CD ringan, dan cukup untuk menguji kamera di iPhone.

Alasannya:

- **Tanpa database.** Hasil PoC adalah file foto dan log metrik, bukan data aplikasi. Skema database yang dibuat sekarang kemungkinan besar harus diubah setelah G0, misalnya opsi C menambah field lensa dan eksposur.
- **Tanpa production.** PoC belum punya login, enkripsi, audit log, atau consent. Memasukkan foto pasien nyata ke sistem seperti itu melanggar prinsip Bagian 9. Production pertama kali dibutuhkan di Fase 2 (alpha, tag `v0.1`), setelah G1 lolos.

**Komponen yang perlu dan tidak perlu di Fase 0**

| Komponen | Perlu? | Catatan |
| --- | --- | --- |
| Repo Git privat | Ya | Folder `poc/camera` dan `packages/slots` |
| Hosting statis HTTPS + deploy otomatis per push/PR | Ya | Syarat kamera web di iPhone |
| Pembatas akses URL (password/akses terbatas) | Ya | URL PoC tidak boleh terbuka publik |
| CI: lint + build | Ya, minimal | Unit test belum wajib |
| Basis data | Tidak | Diganti log JSON/CSV di perangkat |
| Backend/API | Tidak | Spike Sheets API cukup script lokal |
| Object storage cloud | Tidak | Foto diekspor sebagai ZIP dari perangkat |
| Staging + production | Tidak | Mulai Fase 1 dan Fase 2 |

**Langkah implementasi**

1. **Hari 1 — Repo dan deploy.** Buat repo privat, proteksi `main`, dan scaffold `poc/camera` dengan Vite + TypeScript tanpa framework UI. Hubungkan ke hosting statis yang mendukung preview per PR dan pembatas akses. Tambahkan workflow CI: lint dan build. Hasil: URL HTTPS yang terbuka di Safari iPhone.
2. **Hari 1–2 — Data slot.** Tulis `packages/slots/slots.v1.json` berisi 8 slot: kode, label, orientasi, rasio frame, path SVG overlay, flag `mirror` (oklusal), dan checklist. Halaman PoC membaca file ini, sehingga overlay bisa diperbaiki tanpa mengubah kode.
3. **Hari 2–4 — Mode A (kamera web + overlay).**
   1. Minta stream kamera belakang dengan resolusi ideal tinggi, lalu catat resolusi yang benar-benar diberikan (`track.getSettings()`).
   2. Setelah izin diberikan, panggil `enumerateDevices()` dan tampilkan dropdown kamera untuk mencoba lensa telefoto/ultra wide lewat `deviceId`.
   3. Baca `track.getCapabilities()`; tampilkan slider zoom, focusMode, dan torch hanya bila tersedia.
   4. Elemen `<video>` wajib `playsinline muted autoplay` agar berjalan di iOS. Overlay SVG ditumpuk di atas video dengan rasio yang sama.
   5. Capture: gambar frame video ke canvas berukuran `videoWidth × videoHeight`, simpan JPEG kualitas 0,95. Deteksi juga apakah `ImageCapture` tersedia dan catat hasilnya.
   6. Indikator waterpass dari `DeviceOrientationEvent`; di iOS izin diminta lewat tombol (gesture pengguna).
4. **Hari 3 — Mode B (kamera bawaan).** Tombol `<input type="file" accept="image/*" capture="environment">`. Baca EXIF file hasilnya (mis. library `exifr`) untuk mencatat lensa dan focal length yang dipakai kamera bawaan. Ini menunjukkan lensa apa yang "seharusnya" dicapai mode A.
5. **Hari 4–5 — Log dan ekspor.** Setiap foto menghasilkan satu baris log: perangkat, mode, slot, nomor ulangan, resolusi, label kamera, settings JSON, durasi capture. Foto + log disimpan di IndexedDB, lalu tombol "Ekspor ZIP" (mis. `JSZip`) mengirimnya ke laptop via AirDrop/Files. Ekspor setelah setiap sesi karena iOS dapat menghapus penyimpanan web. \[medium confidence\]
6. **Hari 5 — Mode PWA.** Tambahkan manifest dan service worker minimal agar halaman bisa di-"Add to Home Screen", lalu uji ulang izin kamera dalam mode standalone.
7. **Paralel, ½–1 hari — Spike Sheets API.** Script Node lokal dengan service account berhak Viewer pada **salinan** sheet berisi data dummy, bukan sheet ERP asli. Ukur: baca 1 pasien per No. RM, latensi, perilaku bila No. RM duplikat. Kunci service account tidak pernah di-commit dan tidak di-deploy.
8. **Minggu 2 — Eksekusi uji.** Jalankan protokol di atas. Script kecil mengacak nama file sebelum dinilai dokter; tabel pemetaan disimpan terpisah agar penilaian tetap blind. Dokter menilai di spreadsheet skor.
9. **Akhir minggu 2 — G0.** Tulis matriks keputusan, tag repo `poc-v0`, bekukan folder `poc/`, lalu hapus foto relawan dari perangkat dan hosting.

Inti mode A cukup beberapa baris; contoh berikut sengaja minimal dan belum menangani error:

```ts
const stream = await navigator.mediaDevices.getUserMedia({
  video: { facingMode: { ideal: "environment" }, width: { ideal: 4032 }, height: { ideal: 3024 } },
  audio: false,
});
const track = stream.getVideoTracks()[0];
log({ settings: track.getSettings(), caps: track.getCapabilities?.() });
video.srcObject = stream; // <video playsinline muted autoplay>

function capture(): Promise<Blob> {
  const c = document.createElement("canvas");
  c.width = video.videoWidth; c.height = video.videoHeight;
  c.getContext("2d")!.drawImage(video, 0, 0);
  return new Promise(r => c.toBlob(b => r(b!), "image/jpeg", 0.95));
}
```

**Kapan database dan CI/CD production dibutuhkan:** Fase 1 membangun database (dengan migrasi berversi), backend, dan pipeline staging. Production baru dibuat di akhir Fase 1 dan pertama kali menerima data pasien pada rilis alpha `v0.1` di Fase 2, setelah G1. Urutannya: production ada karena data pasien nyata akan masuk, bukan karena database ada.

### 11.3 Version control

Gunakan satu repo Git privat (monorepo) dengan trunk-based development: `main` selalu siap deploy, branch fitur berumur pendek, dan rilis ditandai dengan tag. GitFlow tidak disarankan untuk tim kecil dengan 2 pengguna dokter; overhead branch `develop`/`release` tidak sebanding manfaatnya.

**Struktur repo**

| Folder | Isi | Dibuat di fase |
| --- | --- | --- |
| `docs` | SRS dan catatan requirement; tetap diperbarui | Fase 0 |
| `poc/camera` | Halaman uji PoC kamera, dibekukan setelah G0 | Fase 0 |
| `apps/web` | PWA (UI, capture, galeri, report) | Fase 1 |
| `apps/api` | Backend: auth, data, storage, integrasi Sheets | Fase 1 |
| `packages/slots` | Definisi slot dan overlay sebagai data JSON berversi | Fase 0 (dari PoC) |
| `packages/report` | Template PDF case report dan report progres | Fase 3 |
| `apps/ios` | Shell hybrid (mis. Capacitor), hanya bila G0 memilih opsi C. **Tidak dibuat**: G0 memilih opsi A | — |
| `infra` | Konfigurasi deploy, migrasi database | Fase 1 |

**Aturan branching dan commit**

1. `main` diproteksi: merge hanya lewat pull request, CI wajib hijau, minimal 1 reviewer.
2. Branch fitur bernama `feat/<id>-<ringkas>` atau `fix/<id>-<ringkas>`, mengacu ID requirement (mis. `feat/FR-07-upload-multi`).
3. Commit mengikuti Conventional Commits (`feat:`, `fix:`, `chore:`) agar changelog bisa dibuat otomatis.
4. Fitur yang belum selesai digabung di balik feature flag (mis. ghost overlay), bukan ditahan di branch panjang.
5. Rilis = tag semver: `v0.x` untuk alpha/beta, `v1.0.0` untuk MVP produksi.

**Dua aturan khusus untuk sistem ini**

- **Overlay dan template slot berversi.** Setiap perubahan file di `packages/slots` menaikkan versinya, dan setiap foto menyimpan `overlay_version`. Tanpa ini, perbandingan before/after bisa diam-diam memakai guide yang berbeda.
- **Tidak ada rahasia dan data pasien di repo.** File service account Google, kunci storage, dan foto uji disimpan di secret manager/CI secrets; `.gitignore` dan pemindai rahasia aktif sejak commit pertama.

### 11.4 CI/CD

CI/CD dibangun bertahap. Di Fase 0 cukup satu hal: **preview deploy HTTPS otomatis per pull request**, karena kamera web di iPhone hanya bisa diuji lewat HTTPS, bukan dari laptop developer. Pipeline penuh menyusul di Fase 1.

**Pipeline per event**

| Event | Langkah | Hasil | Mulai fase |
| --- | --- | --- | --- |
| Push ke PR | Lint, typecheck, build, deploy preview HTTPS | URL preview untuk dibuka di iPhone | Fase 0 |
| Push ke PR | Unit test, pemindai rahasia, audit dependensi, cek PWA (manifest, service worker) | Status CI wajib hijau sebelum merge | Fase 1 |
| Merge ke `main` | Build, migrasi database staging, deploy staging, test E2E alur non-kamera | Staging selalu = `main` terbaru | Fase 1 |
| Tag `v*` | Persetujuan manual, backup database, migrasi produksi, deploy produksi, smoke test | Rilis produksi | Fase 2 (alpha) |
| Tag `v*` + opsi C | Build iOS di runner macOS, unggah ke distribusi internal Apple | Build app iPhone | Setelah G0, bila C |

**Environment**

| Environment | Data | Siapa |
| --- | --- | --- |
| Lokal | Data sintetis | Developer |
| Preview (per PR) | Data sintetis | Developer, tester di iPhone |
| Staging | Data sintetis + foto relawan dengan consent; **tidak boleh** foto pasien | Developer, 2 dokter untuk UAT |
| Produksi | Data pasien nyata, region cloud Jakarta (Bagian 9) | Dokter, asisten, admin |

**Batasan pengujian otomatis yang harus diterima.** Test E2E otomatis (mis. Playwright dengan engine WebKit) tidak sama dengan Safari di iPhone asli, dan kamera nyata tidak bisa diotomatisasi dengan andal. \[medium confidence\] Karena itu setiap rilis yang menyentuh capture wajib lolos **checklist uji manual** di kedua iPhone: izin kamera, 11 slot, upload, rotasi/flip, offline lalu online.

**Strategi update PWA.** Service worker baru tidak boleh memaksa reload saat asisten sedang di tengah sesi capture. Versi baru diunduh di latar belakang, lalu pengguna diberi banner "Versi baru tersedia" yang hanya aktif di luar layar capture. Versi aplikasi dan `overlay_version` ditampilkan di menu Tentang untuk memudahkan pelacakan masalah.

**Rollback.** Deploy ulang tag sebelumnya. Migrasi database harus bisa mundur (atau hanya menambah kolom/tabel) agar rollback aplikasi tidak merusak data.

Tool spesifik (mis. GitHub Actions atau GitLab CI, penyedia hosting) sengaja tidak dikunci di dokumen ini; pilih berdasarkan yang sudah dipakai tim. Harga dan fitur layanan tersebut perlu verifikasi live.
