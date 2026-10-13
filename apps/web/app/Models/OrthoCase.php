<?php

namespace App\Models;

use App\Enums\Stage;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kasus ortodonti (FR-04). Satu pasien bisa punya lebih dari satu kasus, mis. retreatment. */
#[Fillable(['patient_id', 'brace_type', 'installed_on', 'removed_on', 'dentist_user_id', 'status'])]
class OrthoCase extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['installed_on' => 'date', 'removed_on' => 'date'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dentist_user_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PhotoSession::class);
    }

    /** Nomor kontrol berikutnya untuk sesi Kontrol baru (FR-05). */
    public function nextControlNumber(): int
    {
        return (int) $this->sessions()->where('stage', Stage::Control)->max('control_number') + 1;
    }
}
