<?php

use Database\Seeders\SlotTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Kontrol: IO-06/IO-07 jadi opsional (config/slots.php). Tabel slot disamakan, lalu status sesi kontrol dihitung
     * ulang agar sesi yang hanya kurang IO-06/IO-07 berubah dari draft menjadi lengkap.
     */
    public function up(): void
    {
        (new SlotTemplateSeeder)->run();

        $required = DB::table('slot_templates')->where('stage', 'control')->where('required', true)->pluck('slot_code')->all();

        DB::table('photo_sessions')->where('stage', 'control')->pluck('id')->each(function ($sessionId) use ($required) {
            $filled = DB::table('photos')->where('photo_session_id', $sessionId)->whereNull('replaced_by_photo_id')->pluck('slot_code')->all();
            $complete = array_diff($required, $filled) === [];
            DB::table('photo_sessions')->where('id', $sessionId)->update(['status' => $complete ? 'complete' : 'draft']);
        });
    }

    public function down(): void
    {
        // Aturan slot mengikuti config/slots.php; tidak dikembalikan.
    }
};
