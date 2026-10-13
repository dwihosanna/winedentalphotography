<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Foto atau rontgen di satu slot sesi. File asli tidak pernah ditimpa (FR-13); retake membuat baris baru
 * dan mengisi replaced_by_photo_id pada foto lama (FR-10). Lokasi file = disk + path (siap object storage).
 */
#[Fillable([
    'photo_session_id', 'slot_code', 'category', 'source', 'disk', 'original_path', 'edited_path', 'edit_params',
    'mime', 'width', 'height', 'original_bytes', 'edited_bytes', 'sha256', 'device', 'lens_mode', 'zoom',
    'overlay_version', 'taken_by', 'taken_at',
])]
class Photo extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (Photo $photo) {
            foreach (['original_path', 'sha256', 'original_bytes', 'photo_session_id', 'slot_code'] as $field) {
                if ($photo->isDirty($field)) {
                    throw new LogicException("Foto asli tidak boleh diubah ($field).");
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'edit_params' => 'array',
            'original_bytes' => 'integer',
            'edited_bytes' => 'integer',
            'zoom' => 'float',
            'taken_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PhotoSession::class, 'photo_session_id');
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'replaced_by_photo_id');
    }

    /** Yang dihitung kuota: file asli + hasil edit (thumbnail tidak) (FR-34). */
    public function storedBytes(): int
    {
        return $this->original_bytes + $this->edited_bytes;
    }
}
