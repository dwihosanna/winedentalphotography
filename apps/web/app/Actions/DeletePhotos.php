<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Photo;
use App\Models\PhotoSession;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
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
     * @param  Photo|null  $only  hanya foto ini beserta riwayat retake-nya (satu foto di slot multi-foto FREE)
     * @return int jumlah slot yang dikosongkan
     */
    public function __invoke(PhotoSession $session, ?array $slotCodes = null, ?Photo $only = null): int
    {
        $files = [];

        $count = DB::transaction(function () use ($session, $slotCodes, $only, &$files) {
            $photos = $session->photos()
                ->when($slotCodes !== null, fn ($q) => $q->whereIn('slot_code', $slotCodes))
                ->when($only !== null, fn ($q) => $q->where('slot_code', $only->slot_code))
                ->lockForUpdate()->get();
            if ($only !== null) {
                $photos = $this->withRetakeHistory($photos, $only);
            }
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

    /**
     * Foto ini dan foto-foto lama yang (berantai) digantikannya.
     *
     * @param  Collection<int, Photo>  $slotPhotos
     * @return Collection<int, Photo>
     */
    private function withRetakeHistory(Collection $slotPhotos, Photo $photo): Collection
    {
        $ids = [$photo->id];
        do {
            $before = count($ids);
            $ids = array_unique([...$ids, ...$slotPhotos->whereIn('replaced_by_photo_id', $ids)->modelKeys()]);
        } while (count($ids) > $before);

        return $slotPhotos->whereIn('id', $ids)->values();
    }
}
