<?php

use Database\Seeders\SlotTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 18 Okt 2026, permintaan dokter: Awal wajib semua slot kecuali FREE (panoramik + sefalometri ikut wajib),
     * Insersi wajib foto wajah + intraoral. Status sesi Awal dan Insersi yang sudah ada dihitung ulang.
     */
    public function up(): void
    {
        (new SlotTemplateSeeder)->run();

        foreach (['initial', 'insertion'] as $stage) {
            $required = DB::table('slot_templates')->where('stage', $stage)->where('required', true)->pluck('slot_code')->all();
            DB::table('photo_sessions')->where('stage', $stage)->pluck('id')->each(function ($sessionId) use ($required) {
                $filled = DB::table('photos')->where('photo_session_id', $sessionId)->whereNull('replaced_by_photo_id')->pluck('slot_code')->all();
                DB::table('photo_sessions')->where('id', $sessionId)->update(['status' => array_diff($required, $filled) === [] ? 'complete' : 'draft']);
            });
        }
    }

    public function down(): void
    {
        // Aturan lama ada di riwayat git config/slots.php; jalankan SlotTemplateSeeder setelah mengembalikannya.
    }
};
