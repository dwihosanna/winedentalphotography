<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Photo;
use App\Models\PhotoSession;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Hapus permanen foto di slot sesi beserta riwayat retake-nya (file asli, hasil edit, thumbnail).
 * Pemakaian penyimpanan dikurangi, status sesi dan foto profil pasien dihitung ulang; tiap foto dicatat di audit log.
 */
class DeletePhotos
{
    /**
     * @param  list<string>|null  $slotCodes  null = semua slot sesi
     * @return int jumlah slot yang dikosongkan
     */
    public function __invoke(PhotoSession $session, ?array $slotCodes = null): int
    {
        $files = [];

        $count = DB::transaction(function () use ($session, $slotCodes, &$files) {
            $photos = $session->photos()
                ->when($slotCodes !== null, fn ($q) => $q->whereIn('slot_code', $slotCodes))
                ->lockForUpdate()->get();
            if ($photos->isEmpty()) {
                return 0;
            }

            foreach ($photos as $photo) {
                AuditLog::record('delete', $photo, ['slot' => $photo->slot_code, 'sha256' => $photo->sha256, 'session' => $session->id]);
                $files[$photo->disk][] = $photo->original_path;
                array_push($files[$photo->disk], ...array_filter([$photo->edited_path, $photo->thumb_path]));
            }

            // Lepas referensi foreign key (foto profil pasien, rantai retake) sebelum baris dihapus.
            $patient = $session->orthoCase->patient;
            if (in_array($patient->avatar_photo_id, $photos->modelKeys(), true)) {
                $patient->forceFill(['avatar_photo_id' => null])->save();
            }
            Photo::whereKey($photos->modelKeys())->update(['replaced_by_photo_id' => null]);
            Photo::whereKey($photos->modelKeys())->delete();

            Workspace::whereKey($session->workspace_id)->first()->addUsedBytes(-$photos->sum(fn (Photo $p) => $p->storedBytes()));
            $session->refreshStatus();
            if ($photos->contains('slot_code', 'EO-02')) {
                $patient->refreshAvatar();
            }

            return $photos->unique('slot_code')->count();
        });

        // File dihapus setelah commit: transaksi gagal tidak meninggalkan baris tanpa file.
        foreach ($files as $disk => $paths) {
            Storage::disk($disk)->delete($paths);
        }

        return $count;
    }
}
