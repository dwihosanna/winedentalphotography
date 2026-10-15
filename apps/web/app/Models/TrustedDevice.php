<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Perangkat tempat pengguna memilih "Percayai perangkat ini" (lihat App\Support\TrustedDevices). */
#[Fillable(['user_id', 'token_hash', 'name', 'last_used_at', 'expires_at'])]
class TrustedDevice extends Model
{
    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
