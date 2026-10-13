<?php

namespace App\Models;

use App\Enums\Plan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['practice_name', 'owner_user_id', 'plan', 'patient_limit', 'storage_limit_bytes', 'status'])]
class Workspace extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'plan' => Plan::class,
            'patient_limit' => 'integer',
            'storage_limit_bytes' => 'integer',
            'used_bytes' => 'integer',
            'plan_changed_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /** Pasien demo tidak dihitung (FR-34). Dicek di server sebelum menyimpan pasien baru. */
    public function canAddPatient(): bool
    {
        if ($this->patient_limit === null) {
            return true;
        }

        $count = Patient::withoutGlobalScopes()->where('workspace_id', $this->id)->where('is_demo', false)->count();

        return $count < $this->patient_limit;
    }

    public function hasStorageFor(int $bytes): bool
    {
        return $this->storage_limit_bytes === null || $this->used_bytes + $bytes <= $this->storage_limit_bytes;
    }

    /** Persen pemakaian penyimpanan untuk peringatan 80% dan 100%; null bila tanpa batas. */
    public function storageUsedPercent(): ?int
    {
        return $this->storage_limit_bytes ? (int) floor($this->used_bytes * 100 / $this->storage_limit_bytes) : null;
    }

    /** Tambah/kurangi pemakaian secara atomik (dipanggil di transaksi yang sama dengan simpan file). */
    public function addUsedBytes(int $bytes): void
    {
        static::whereKey($this->id)->increment('used_bytes', $bytes);
        $this->refresh();
    }

    public function changePlan(Plan $plan, User $by): void
    {
        $this->forceFill([
            'plan' => $plan,
            'patient_limit' => $plan->patientLimit(),
            'storage_limit_bytes' => $plan->storageLimitBytes(),
            'plan_changed_by' => $by->id,
            'plan_changed_at' => now(),
        ])->save();
    }
}
