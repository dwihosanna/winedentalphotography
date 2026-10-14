<?php

namespace App\Models;

use App\Enums\Stage;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** Sesi foto per kunjungan (FR-05, FR-09). Tahap menentukan slot dan mana yang wajib. */
#[Table('photo_sessions')]
#[Fillable(['ortho_case_id', 'stage', 'control_number', 'session_date', 'notes', 'created_by'])]
class PhotoSession extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['stage' => Stage::class, 'session_date' => 'date'];
    }

    public function orthoCase(): BelongsTo
    {
        return $this->belongsTo(OrthoCase::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    /** Foto yang berlaku: yang belum diganti retake. */
    public function currentPhotos(): HasMany
    {
        return $this->photos()->whereNull('replaced_by_photo_id');
    }

    /** @return Collection<int, SlotTemplate> */
    public function slots(): Collection
    {
        return SlotTemplate::where('stage', $this->stage)->orderBy('sort_order')->get();
    }

    /** @return list<string> kode slot wajib yang belum punya foto */
    public function missingRequiredSlots(): array
    {
        $filled = $this->currentPhotos()->pluck('slot_code')->all();

        return $this->slots()->where('required', true)->pluck('slot_code')->diff($filled)->values()->all();
    }

    /**
     * Status "lengkap" hanya bila semua slot wajib terisi; selain itu draft (FR-09).
     * Tahap tanpa slot wajib (non-ortho) lengkap bila sudah ada minimal satu foto.
     */
    public function refreshStatus(): void
    {
        $hasRequired = $this->slots()->contains('required', true);
        $complete = $this->missingRequiredSlots() === [] && ($hasRequired || $this->currentPhotos()->exists());
        $this->status = $complete ? 'complete' : 'draft';
        $this->save();
    }

    public function stageLabel(): string
    {
        return $this->stage === Stage::Control ? 'Kontrol ke-'.$this->control_number : $this->stage->label();
    }
}
