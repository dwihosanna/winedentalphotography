<?php

namespace App\Models;

use App\Enums\Stage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Slot per tahap + wajib/opsional (SRS 6.1). Konfigurasi global, diisi dari config/slots.php. */
#[Fillable(['stage', 'slot_code', 'label', 'category', 'kind', 'required', 'sort_order'])]
class SlotTemplate extends Model
{
    protected function casts(): array
    {
        return ['stage' => Stage::class, 'required' => 'boolean'];
    }
}
