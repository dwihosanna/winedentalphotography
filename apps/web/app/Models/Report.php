<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Case report / report progres (FR-20, FR-21, FR-26). PDF disimpan sebagai snapshot. */
#[Fillable(['ortho_case_id', 'template', 'before_session_id', 'after_session_id', 'selected_photos', 'anonymized', 'created_by'])]
class Report extends Model
{
    use BelongsToWorkspace, HasUuids;

    protected function casts(): array
    {
        return ['selected_photos' => 'array', 'anonymized' => 'boolean', 'pdf_bytes' => 'integer'];
    }

    public function orthoCase(): BelongsTo
    {
        return $this->belongsTo(OrthoCase::class);
    }
}
