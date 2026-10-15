<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Sekali jalan: nomor "Kontrol ke-n" yang sudah ada disamakan dengan urutan tanggal kunjungan (lihat OrthoCase::renumberControls). */
    public function up(): void
    {
        DB::table('photo_sessions')->where('stage', 'control')->distinct()->pluck('ortho_case_id')->each(function ($caseId) {
            DB::table('photo_sessions')->where('ortho_case_id', $caseId)->where('stage', 'control')
                ->orderBy('session_date')->orderBy('created_at')->orderBy('id')->pluck('id')
                ->each(fn ($id, $i) => DB::table('photo_sessions')->where('id', $id)->update(['control_number' => $i + 1]));
        });
    }

    public function down(): void
    {
        // Nomor lama tidak disimpan; penomoran menurut tanggal tetap berlaku.
    }
};
