<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Photo;
use App\Models\Workspace;
use App\Support\JpegMetadata;
use App\Support\PhotoEdit;
use App\Support\Thumbnail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Simpan atau reset edit foto (FR-13, FR-14). Hasil edit = file terpisah; file asli tidak pernah diubah.
 * Ukuran file edit ikut dihitung kuota (FR-34); thumbnail galeri mengikuti versi yang tampil.
 */
class EditPhoto
{
    /** @param  array<string, mixed>|null  $params  null = reset ke foto asli */
    public function __invoke(Photo $photo, ?array $params): Photo
    {
        if (! $photo->isImage()) {
            throw ValidationException::withMessages(['params' => 'Hanya foto yang bisa diedit.']);
        }

        $params = $params === null ? null : PhotoEdit::normalize($params);
        $disk = Storage::disk($photo->disk);
        $original = $disk->get($photo->original_path);
        $dir = dirname($photo->original_path);
        $thumbPath = $photo->thumb_path ?? "$dir/{$photo->id}_thumb.jpg";

        $edited = $params === null || PhotoEdit::isIdentity($params) ? null : PhotoEdit::render($original, $params);
        $editedPath = $edited === null ? null : "$dir/{$photo->id}_edit_".Str::lower(Str::random(8)).'.jpg';
        if ($edited !== null) {
            $disk->put($editedPath, $edited);
        }

        $oldEditedPath = $photo->edited_path;
        $oldThumb = $disk->exists($thumbPath) ? $disk->get($thumbPath) : null;

        try {
            DB::transaction(function () use ($photo, $params, $edited, $editedPath, $original, $disk, $thumbPath) {
                $delta = strlen($edited ?? '') - $photo->edited_bytes;
                $workspace = Workspace::whereKey($photo->workspace_id)->lockForUpdate()->first();
                if ($delta > 0 && ! $workspace->hasStorageFor($delta)) {
                    throw ValidationException::withMessages(['params' => 'Penyimpanan penuh: hasil edit tidak bisa disimpan. Foto asli tidak berubah.']);
                }

                $thumb = Thumbnail::make($edited ?? $original, $edited === null ? JpegMetadata::orientationOf($original) : 1, config('dental.thumb_size'));
                if ($thumb !== null) {
                    $disk->put($thumbPath, $thumb);
                }

                $photo->forceFill([
                    'edited_path' => $editedPath,
                    'edited_bytes' => strlen($edited ?? ''),
                    // Parameter tetap disimpan walau identitas, agar "auto-flip dimatikan" tidak diterapkan ulang.
                    'edit_params' => $params,
                    'thumb_path' => $thumb !== null ? $thumbPath : $photo->thumb_path,
                ])->save();
                $workspace->addUsedBytes($delta);

                AuditLog::record($params === null ? 'edit_reset' : 'edit', $photo, array_filter(['params' => $params]));
            });
        } catch (Throwable $e) {
            if ($editedPath !== null) {
                $disk->delete($editedPath);
            }
            if ($oldThumb !== null) {
                $disk->put($thumbPath, $oldThumb);
            }
            throw $e;
        }

        if ($oldEditedPath !== null) {
            $disk->delete($oldEditedPath);
        }

        return $photo;
    }
}
