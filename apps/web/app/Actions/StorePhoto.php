<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Photo;
use App\Models\PhotoSession;
use App\Models\SlotTemplate;
use App\Models\Workspace;
use App\Support\JpegMetadata;
use App\Support\ReleaseDatabase;
use App\Support\SlotGuides;
use App\Support\Thumbnail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Simpan satu foto/rontgen ke slot sesi; dipakai kamera (FR-06) dan unggah (FR-07, FR-08).
 * File asli disimpan sekali dan tidak pernah ditimpa; retake membuat baris baru (FR-10).
 */
class StorePhoto
{
    public function __construct(private EditPhoto $editPhoto) {}

    /**
     * @param  array{source: string, device?: ?string, lens_mode?: ?string, zoom?: ?float, overlay_version?: ?string}  $meta
     */
    public function __invoke(PhotoSession $session, string $slotCode, UploadedFile $file, array $meta, int $userId): Photo
    {
        $slot = SlotTemplate::where('stage', $session->stage)->where('slot_code', $slotCode)->first();
        if ($slot === null) {
            throw ValidationException::withMessages(['slot_code' => 'Slot ini tidak ada di tahap sesi ini.']);
        }

        $mime = $file->getMimeType();
        $allowed = $slot->kind === 'xray' ? ['image/jpeg', 'image/png', 'application/pdf'] : ['image/jpeg', 'image/png'];
        if (! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages(['file' => $mime === 'image/heic' || $mime === 'image/heif'
                ? 'Format HEIC belum didukung. Di iPhone: Pengaturan > Kamera > Format > Paling Kompatibel, atau bagikan foto sebagai JPEG.'
                : 'Format file harus '.($slot->kind === 'xray' ? 'JPG, PNG, atau PDF.' : 'JPG atau PNG.')]);
        }

        // Pemrosesan gambar bisa beberapa detik: jangan tahan koneksi MySQL (dibatasi hosting) selama itu.
        ReleaseDatabase::now();
        [$bytes, $info] = $this->prepare($file->getRealPath(), $mime);
        $disk = config('dental.photo_disk');
        $id = (string) Str::uuid();
        $dir = "{$session->workspace_id}/{$session->id}";
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'][$mime];
        $originalPath = "$dir/$id.$ext";
        $thumbPath = null;

        Storage::disk($disk)->put($originalPath, $bytes);
        if ($mime !== 'application/pdf' && ($thumb = Thumbnail::make($bytes, $info['orientation'], config('dental.thumb_size')))) {
            $thumbPath = "$dir/{$id}_thumb.jpg";
            Storage::disk($disk)->put($thumbPath, $thumb);
        }

        try {
            $photo = DB::transaction(function () use ($session, $slot, $file, $meta, $userId, $bytes, $info, $mime, $disk, $id, $originalPath, $thumbPath) {
                // Kunci baris workspace agar unggahan bersamaan tidak melewati batas penyimpanan (FR-34).
                $workspace = Workspace::whereKey($session->workspace_id)->lockForUpdate()->first();
                if (! $workspace->hasStorageFor(strlen($bytes))) {
                    throw ValidationException::withMessages(['file' => 'Penyimpanan akun Free (5 GB) penuh. Foto lama tetap bisa dilihat dan diekspor; ajukan Premium untuk menambah.']);
                }

                $previous = $session->currentPhotos()->where('slot_code', $slot->slot_code)->lockForUpdate()->first();

                $photo = new Photo([
                    'photo_session_id' => $session->id,
                    'slot_code' => $slot->slot_code,
                    'category' => $slot->category,
                    'source' => $meta['source'],
                    'disk' => $disk,
                    'original_path' => $originalPath,
                    'mime' => $mime,
                    'width' => $info['width'],
                    'height' => $info['height'],
                    'original_bytes' => strlen($bytes),
                    'sha256' => hash('sha256', $bytes),
                    'device' => $meta['device'] ?? $info['model'],
                    'lens_mode' => $meta['lens_mode'] ?? null,
                    'zoom' => $meta['zoom'] ?? null,
                    'overlay_version' => $meta['overlay_version'] ?? null,
                    'taken_by' => $userId,
                    'taken_at' => now(),
                ]);
                $photo->id = $id;
                $photo->forceFill([
                    'thumb_path' => $thumbPath,
                    'exif_taken_at' => $info['taken_at'],
                    'original_name' => $meta['source'] === 'upload' ? mb_substr($file->getClientOriginalName(), 0, 255) : null,
                ])->save();

                if ($previous) {
                    $previous->forceFill(['replaced_by_photo_id' => $photo->id])->save();
                }

                $workspace->addUsedBytes(strlen($bytes));
                $session->refreshStatus();
                $this->updateAvatar($session, $photo);
                AuditLog::record($previous ? 'replace' : 'upload', $photo, array_filter([
                    'slot' => $slot->slot_code, 'source' => $meta['source'], 'replaced' => $previous?->id,
                ]));

                return $photo;
            });
        } catch (Throwable $e) {
            Storage::disk($disk)->delete(array_filter([$originalPath, $thumbPath]));
            throw $e;
        }

        $this->autoFlip($photo, $meta['source']);

        return $photo;
    }

    /**
     * Foto unggahan di slot cermin (oklusal) dibalik otomatis lewat edit non-destruktif (FR-14).
     * Foto kamera tidak: tombol Cermin di kamera sudah membalik hasil saat dipotret.
     */
    private function autoFlip(Photo $photo, string $source): void
    {
        $mirror = SlotGuides::mirror($photo->slot_code);
        if ($source !== 'upload' || ! $photo->isImage() || ! ($mirror['default'] ?? false)) {
            return;
        }

        try {
            ($this->editPhoto)($photo, [$mirror['axis'] === 'vertical' ? 'flip_v' : 'flip_h' => true, 'auto_flip' => true]);
        } catch (ValidationException) {
            // Kuota tidak cukup untuk file edit: foto asli tetap tersimpan tanpa dibalik, bisa diedit nanti.
        }
    }

    /** @return array{0: string, 1: array{orientation: int, taken_at: ?string, model: ?string, width: ?int, height: ?int}} */
    private function prepare(string $path, string $mime): array
    {
        $bytes = file_get_contents($path);
        $info = ['orientation' => 1, 'taken_at' => null, 'model' => null];

        if ($mime === 'image/jpeg') {
            $info = JpegMetadata::read($path);
            // GPS dan metadata lain dibuang sebelum disimpan (SRS 7, metadata EXIF).
            $bytes = JpegMetadata::strip($bytes, $info['orientation']) ?? throw ValidationException::withMessages(['file' => 'File JPEG rusak atau tidak lengkap.']);
        }

        $size = $mime === 'application/pdf' ? false : @getimagesizefromstring($bytes);
        // Lebar/tinggi tampilan: tertukar bila orientasi EXIF memutar 90°.
        [$w, $h] = $size ? [$size[0], $size[1]] : [null, null];
        if (in_array($info['orientation'], [5, 6, 7, 8], true)) {
            [$w, $h] = [$h, $w];
        }

        return [$bytes, $info + ['width' => $w, 'height' => $h]];
    }

    /** Foto profil pasien = EO-01 dari sesi terbaru (FR-32). */
    private function updateAvatar(PhotoSession $session, Photo $photo): void
    {
        if ($photo->slot_code !== 'EO-01') {
            return;
        }

        $patient = $session->orthoCase->patient;
        $current = $patient->avatarPhoto?->session;
        if ($current === null || $current->session_date->lte($session->session_date)) {
            $patient->forceFill(['avatar_photo_id' => $photo->id])->save();
        }
    }
}
