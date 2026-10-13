<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** No. RM per sistem RME; unik per (workspace, sistem, nilai) (SRS 5.2). */
#[Fillable(['patient_id', 'system', 'value', 'source', 'synced_at'])]
class PatientExternalId extends Model
{
    use BelongsToWorkspace;

    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
