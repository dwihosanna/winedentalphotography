<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Sekali jalan: foto profil semua pasien = EO-02 pertama (sebelumnya EO-01 terbaru). Lihat Patient::refreshAvatar. */
    public function up(): void
    {
        DB::table('patients')->orderBy('id')->pluck('id')->each(function ($patientId) {
            $photoId = DB::table('photos')
                ->join('photo_sessions', 'photo_sessions.id', '=', 'photos.photo_session_id')
                ->join('ortho_cases', 'ortho_cases.id', '=', 'photo_sessions.ortho_case_id')
                ->where('ortho_cases.patient_id', $patientId)
                ->where('photos.slot_code', 'EO-02')
                ->whereNull('photos.replaced_by_photo_id')
                ->where('photos.mime', 'like', 'image/%')
                ->orderBy('photo_sessions.session_date')->orderBy('photos.created_at')
                ->value('photos.id');

            DB::table('patients')->where('id', $patientId)->update(['avatar_photo_id' => $photoId]);
        });
    }

    public function down(): void
    {
        // Pilihan lama (EO-01) tidak disimpan; aturan EO-02 tetap berlaku.
    }
};
