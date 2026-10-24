<?php

use Database\Seeders\SlotTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 24 Okt 2026, permintaan dokter: slot FREE tetap satu, tapi menampung sampai 3 foto (Bebas 1/3 s.d. 3/3).
     * Menggantikan pemecahan FREE-1 s.d. FREE-3 (v1.37): foto dan pilihan report di slot itu kembali ke FREE.
     * Tiap slot mendapat max_photos (default 1 = foto baru mengganti foto lama lewat retake).
     */
    public function up(): void
    {
        Schema::table('slot_templates', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_photos')->default(1)->after('required');
        });

        $free = ['FREE-1', 'FREE-2', 'FREE-3'];
        DB::transaction(function () use ($free) {
            DB::table('photos')->whereIn('slot_code', $free)->update(['slot_code' => 'FREE']);

            DB::table('reports')->get(['id', 'selected_photos'])->each(function ($report) use ($free) {
                $selection = json_decode($report->selected_photos, true) ?: [];
                if (array_intersect($free, array_keys($selection)) === []) {
                    return;
                }
                $merged = [];
                foreach ($selection as $code => $pair) {
                    $merged[in_array($code, $free, true) ? 'FREE' : $code] ??= $pair;
                }
                DB::table('reports')->where('id', $report->id)->update(['selected_photos' => json_encode($merged)]);
            });
        });

        (new SlotTemplateSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('slot_templates', function (Blueprint $table) {
            $table->dropColumn('max_photos');
        });
    }
};
