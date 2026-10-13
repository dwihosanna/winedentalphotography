<?php

return [
    // Versi syarat layanan yang berlaku. Menaikkan versi meminta semua pengguna menyetujui ulang (FR-27).
    'terms_version' => env('TERMS_VERSION', '2026-10-13'),

    // Sistem No. RM default untuk input manual (patient_external_ids.system).
    'default_rm_system' => 'rm',

    // Disk untuk file baru. Kolom disk di tiap baris menyimpan disk asalnya, jadi file lama tetap terbaca.
    'photo_disk' => env('PHOTOS_DISK', 'photos'),

    // Batas unggah per file (KB). Server juga perlu upload_max_filesize/post_max_size yang cukup (public/.user.ini).
    'max_upload_kb' => 40 * 1024,

    // Sisi terpanjang thumbnail galeri (NFR-02).
    'thumb_size' => 480,

    // URL file bertanda tangan berlaku sekian menit (SRS Bagian 9: 5–15 menit).
    'signed_url_minutes' => 10,

    // Peringatan bila tanggal EXIF foto unggahan berbeda lebih dari sekian hari dari tanggal sesi.
    'exif_date_warning_days' => 7,
];
