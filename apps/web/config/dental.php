<?php

return [
    // Versi aplikasi di footer. Naikkan setiap rilis ke produksi (1.01, 1.02, ...).
    'version' => '1.36',

    // "Percayai perangkat ini": tetap masuk sekian hari sejak terakhir dipakai (bergulir).
    'trusted_device_days' => (int) env('TRUSTED_DEVICE_DAYS', 180),

    // Zona waktu tampilan timestamp foto (aplikasi menyimpan waktu dalam UTC).
    'display_timezone' => env('DISPLAY_TIMEZONE', 'Asia/Jakarta'),

    // Font timestamp yang dicetak di foto PDF report (DejaVu ikut terpasang bersama dompdf).
    'timestamp_font' => base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'),

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

    // URL foto di preview presentasi berlaku lebih lama: dokter bisa menjelaskan ke pasien sambil berdiskusi.
    'presentation_url_minutes' => 60,

    // Peringatan bila tanggal EXIF foto unggahan berbeda lebih dari sekian hari dari tanggal sesi.
    'exif_date_warning_days' => 7,

    // Pilihan tipe behel kasus ortho (14 Okt 2026); "Lainnya" di form membuka isian teks bebas.
    'brace_types' => [
        'Konvensional (R)', 'Konvensional (P)', 'Konvensional (C)', 'Konvensional (S)',
        'SL Metal', 'SL Clear', 'Damon',
    ],

    // Pilihan jenis perawatan kasus non-ortho; "Lainnya" membuka isian teks bebas.
    'treatment_types' => ['Tambalan', 'Scaling', 'Bleaching', 'Veneer', 'Crown/Gigi tiruan'],
];
