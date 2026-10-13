<?php

namespace App\Models;

use App\Enums\ConsentType;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['patient_id', 'type', 'given_on', 'valid_until', 'guardian_name', 'proof_disk', 'proof_path', 'recorded_by'])]
class Consent extends Model
{
    use BelongsToWorkspace, HasUuids;

    protected function casts(): array
    {
        return ['type' => ConsentType::class, 'given_on' => 'date', 'valid_until' => 'date', 'revoked_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->valid_until === null || $this->valid_until->endOfDay()->isFuture());
    }
}
