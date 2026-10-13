<?php

return [
    // Versi syarat layanan yang berlaku. Menaikkan versi meminta semua pengguna menyetujui ulang (FR-27).
    'terms_version' => env('TERMS_VERSION', '2026-10-13'),

    // Sistem No. RM default untuk input manual (patient_external_ids.system).
    'default_rm_system' => 'rm',
];
