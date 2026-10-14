<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
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
            'exif_taken_at' => 'datetime',
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

    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by');
    }

    /** URL bertanda tangan berumur pendek; tidak ada URL publik permanen (SRS Bagian 9). */
    public function url(string $variant = 'thumb', ?int $minutes = null): string
    {
        return URL::temporarySignedRoute('photos.file', now()->addMinutes($minutes ?? config('dental.signed_url_minutes')), [
            'photo' => $this->id, 'variant' => $variant,
        ]);
    }

    /**
     * Waktu foto diambil untuk timestamp: tanggal EXIF (foto unggahan, sudah waktu lokal kamera, ditampilkan apa adanya)
     * atau waktu tersimpan (foto kamera aplikasi, UTC, diubah ke zona waktu tampilan).
     */
    public function capturedAt(): CarbonInterface
    {
        return $this->exif_taken_at ?? $this->taken_at->copy()->setTimezone(config('dental.display_timezone'));
    }

    public function timestampText(): string
    {
        return $this->capturedAt()->format('d-m-Y H:i');
    }

    /** Versi yang ditampilkan di galeri, perbandingan, dan report: hasil edit bila ada (FR-13). */
    public function displayVariant(): string
    {
        return $this->edited_path ? 'edited' : 'original';
    }

    /** Dibalik otomatis karena slot cermin dan belum diubah pengguna (FR-14). */
    public function isAutoFlipped(): bool
    {
        return (bool) ($this->edit_params['auto_flip'] ?? false);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /** Tanggal EXIF unggahan jauh dari tanggal sesi → mungkin salah sesi (SRS 7). */
    public function exifDateMismatch(): bool
    {
        return $this->exif_taken_at !== null
            && abs($this->exif_taken_at->startOfDay()->diffInDays($this->session->session_date, false)) > config('dental.exif_date_warning_days');
    }

    /** Yang dihitung kuota: file asli + hasil edit (thumbnail tidak) (FR-34). */
    public function storedBytes(): int
    {
        return $this->original_bytes + $this->edited_bytes;
    }
}
