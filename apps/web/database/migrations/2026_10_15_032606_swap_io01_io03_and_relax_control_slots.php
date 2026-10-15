<?php

use Database\Seeders\SlotTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 15 Okt 2026, permintaan dokter:
     * 1. IO-01 = intraoral kanan, IO-03 = intraoral kiri (dulu sebaliknya). Foto lama dipindahkan sesuai isinya:
     *    foto IO-01 lama (isi: kiri) menjadi IO-03, foto IO-03 lama (isi: kanan) menjadi IO-01; kategori (io_left/io_right)
     *    tetap karena menggambarkan isi foto. Pilihan foto di report lama ikut ditukar; PDF yang sudah jadi tidak berubah.
     * 2. Kontrol: wajib IO-01 s.d. IO-03 saja (IO-04 s.d. IO-07 opsional); status sesi kontrol dihitung ulang.
     */
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('photos')->where('slot_code', 'IO-01')->update(['slot_code' => 'IO-X']);
            DB::table('photos')->where('slot_code', 'IO-03')->update(['slot_code' => 'IO-01']);
            DB::table('photos')->where('slot_code', 'IO-X')->update(['slot_code' => 'IO-03']);

            DB::table('reports')->get(['id', 'selected_photos'])->each(function ($report) {
                $selection = json_decode($report->selected_photos, true) ?: [];
                if (! array_key_exists('IO-01', $selection) && ! array_key_exists('IO-03', $selection)) {
                    return;
                }
                $swapped = [];
                foreach ($selection as $code => $pair) {
                    $swapped[['IO-01' => 'IO-03', 'IO-03' => 'IO-01'][$code] ?? $code] = $pair;
                }
                DB::table('reports')->where('id', $report->id)->update(['selected_photos' => json_encode($swapped)]);
            });
        });

        (new SlotTemplateSeeder)->run();

        $required = DB::table('slot_templates')->where('stage', 'control')->where('required', true)->pluck('slot_code')->all();
        DB::table('photo_sessions')->where('stage', 'control')->pluck('id')->each(function ($sessionId) use ($required) {
            $filled = DB::table('photos')->where('photo_session_id', $sessionId)->whereNull('replaced_by_photo_id')->pluck('slot_code')->all();
            DB::table('photo_sessions')->where('id', $sessionId)->update(['status' => array_diff($required, $filled) === [] ? 'complete' : 'draft']);
        });
    }

    public function down(): void
    {
        // Tidak dikembalikan otomatis; tukar ulang manual bila perlu.
    }
};
