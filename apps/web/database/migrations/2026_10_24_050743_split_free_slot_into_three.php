<?php

use Database\Seeders\SlotTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 24 Okt 2026, permintaan dokter: slot FREE dipecah jadi 3 slot opsional FREE-1, FREE-2, FREE-3 (Bebas 1/3 s.d. 3/3).
     * Foto FREE lama (beserta riwayat retake-nya) menjadi FREE-1, pilihan foto di report lama ikut dipindah. FREE selalu
     * opsional, jadi status sesi tidak berubah.
     */
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('photos')->where('slot_code', 'FREE')->update(['slot_code' => 'FREE-1']);

            DB::table('reports')->get(['id', 'selected_photos'])->each(function ($report) {
                $selection = json_decode($report->selected_photos, true) ?: [];
                if (! array_key_exists('FREE', $selection)) {
                    return;
                }
                $renamed = [];
                foreach ($selection as $code => $pair) {
                    $renamed[$code === 'FREE' ? 'FREE-1' : $code] = $pair;
                }
                DB::table('reports')->where('id', $report->id)->update(['selected_photos' => json_encode($renamed)]);
            });
        });

        (new SlotTemplateSeeder)->run();
    }

    public function down(): void
    {
        // Tidak dikembalikan otomatis; aturan lama ada di riwayat git config/slots.php.
    }
};
