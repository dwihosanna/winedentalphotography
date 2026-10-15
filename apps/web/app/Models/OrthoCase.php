<?php

namespace App\Models;

use App\Enums\CaseKind;
use App\Enums\Stage;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kasus perawatan (FR-04): ortodonti atau non-ortho (tambalan, scaling, dll.).
 * Satu pasien bisa punya lebih dari satu kasus, mis. retreatment atau tambalan di luar perawatan behel.
 */
#[Fillable(['patient_id', 'kind', 'brace_type', 'treatment_type', 'installed_on', 'removed_on', 'dentist_user_id', 'status'])]
class OrthoCase extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['kind' => CaseKind::class, 'installed_on' => 'date', 'removed_on' => 'date'];
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

    public function isOrtho(): bool
    {
        return $this->kind !== CaseKind::General;
    }

    /** Nama singkat kasus di daftar pilihan: tipe behel (ortho) atau jenis perawatan (non-ortho). */
    public function label(): string
    {
        return $this->isOrtho() ? ($this->brace_type ?: 'Ortodonti') : ($this->treatment_type ?: 'Non-ortho');
    }

    /** Nomor sementara untuk sesi Kontrol baru; nomor final ditetapkan renumberControls() (FR-05). */
    public function nextControlNumber(): int
    {
        return (int) $this->sessions()->where('stage', Stage::Control)->max('control_number') + 1;
    }

    /**
     * Nomor "Kontrol ke-n" mengikuti urutan tanggal kunjungan (lama → baru), bukan urutan input: foto kontrol lama
     * yang diunggah belakangan menggeser nomor sesi yang lebih baru. Tanggal sama: urutan dibuat.
     * Memakai update query (tanpa event model) agar tidak memicu penomoran ulang berulang.
     *
     * @return int jumlah sesi yang nomornya berubah
     */
    public function renumberControls(): int
    {
        $changed = 0;
        $sessions = $this->sessions()->where('stage', Stage::Control)
            ->orderBy('session_date')->orderBy('created_at')->orderBy('id')->get(['id', 'control_number']);

        foreach ($sessions->values() as $i => $session) {
            if ($session->control_number !== $i + 1) {
                PhotoSession::whereKey($session->id)->update(['control_number' => $i + 1]);
                $changed++;
            }
        }

        return $changed;
    }
}
