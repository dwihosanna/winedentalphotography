<?php

// Slot foto dan aturan wajib per tahap (SRS 6.1). Disalin ke tabel slot_templates oleh SlotTemplateSeeder;
// admin bisa mengubah tabel itu tanpa rilis ulang. Kode dan label mengikuti poc/camera/slots.v2.json.
//
// Aturan dokter (13 Okt 2026): sesi Awal wajib EO-01–03 + IO-01–07; kunjungan berikutnya wajib IO-01–07,
// EO opsional; FREE selalu opsional. Rontgen (RX) opsional di semua tahap: bisa diunggah di kunjungan berikutnya.
// Kontrol (15 Okt 2026): wajib IO-01 s.d. IO-03; oklusal (IO-04/05) dan anterior (IO-06/07) opsional.
// IO-01 = intraoral kanan, IO-03 = intraoral kiri (ditukar 15 Okt 2026, slots.v2.json 2.3.0).
// Kasus non-ortho (14 Okt 2026): tahap Sebelum/Sesudah, semua slot opsional; sesi lengkap bila ada minimal 1 foto.
// 18 Okt 2026: Awal wajib semua slot kecuali FREE (rontgen RX-01/02 ikut wajib); Insersi wajib EO + IO (rontgen
// dan FREE opsional); Kontrol tetap wajib IO-01 s.d. IO-03. Lepas/Retensi tidak berubah.

$followUp = [
    'EO-01' => false, 'EO-02' => false, 'EO-03' => false,
    'IO-01' => true, 'IO-02' => true, 'IO-03' => true, 'IO-04' => true, 'IO-05' => true, 'IO-06' => true, 'IO-07' => true,
    'FREE' => false, 'RX-01' => false, 'RX-02' => false,
];

$general = array_map(fn () => false, $followUp);

return [
    'slots' => [
        'EO-01' => ['label' => 'Wajah depan, rest', 'category' => 'face_front_rest', 'kind' => 'photo'],
        'EO-02' => ['label' => 'Wajah depan, senyum', 'category' => 'face_front_smile', 'kind' => 'photo'],
        'EO-03' => ['label' => 'Profil kanan (standar ABO)', 'category' => 'face_profile_right', 'kind' => 'photo'],
        'IO-01' => ['label' => 'Intraoral kanan', 'category' => 'io_right', 'kind' => 'photo'],
        'IO-02' => ['label' => 'Intraoral depan', 'category' => 'io_front', 'kind' => 'photo'],
        'IO-03' => ['label' => 'Intraoral kiri', 'category' => 'io_left', 'kind' => 'photo'],
        'IO-04' => ['label' => 'Oklusal atas', 'category' => 'io_occlusal_upper', 'kind' => 'photo'],
        'IO-05' => ['label' => 'Oklusal bawah', 'category' => 'io_occlusal_lower', 'kind' => 'photo'],
        'IO-06' => ['label' => 'Anterior atas', 'category' => 'io_anterior_upper', 'kind' => 'photo'],
        'IO-07' => ['label' => 'Anterior bawah', 'category' => 'io_anterior_lower', 'kind' => 'photo'],
        'FREE' => ['label' => 'Bebas', 'category' => 'other', 'kind' => 'photo'],
        'RX-01' => ['label' => 'Rontgen panoramik', 'category' => 'xray_panoramic', 'kind' => 'xray'],
        'RX-02' => ['label' => 'Rontgen sefalometri', 'category' => 'xray_cephalometric', 'kind' => 'xray'],
    ],

    // true = wajib, false = opsional; slot yang tidak disebut tidak tampil di tahap itu. Urutan = urutan wizard.
    'stages' => [
        'initial' => [
            'EO-01' => true, 'EO-02' => true, 'EO-03' => true,
            'IO-01' => true, 'IO-02' => true, 'IO-03' => true, 'IO-04' => true, 'IO-05' => true, 'IO-06' => true, 'IO-07' => true,
            'FREE' => false, 'RX-01' => true, 'RX-02' => true,
        ],
        'insertion' => array_replace($followUp, ['EO-01' => true, 'EO-02' => true, 'EO-03' => true]),
        'control' => array_replace($followUp, ['IO-04' => false, 'IO-05' => false, 'IO-06' => false, 'IO-07' => false]),
        'debond' => $followUp,
        'retention' => $followUp,
        'before' => $general,
        'after' => $general,
    ],
];
