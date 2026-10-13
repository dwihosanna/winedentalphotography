<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Support\Dpid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\HtmlString;
use LogicException;

#[Fillable(['name', 'birth_date', 'email', 'phone', 'sex', 'is_demo'])]
class Patient extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::creating(function (Patient $patient) {
            $patient->dpid ??= static::uniqueDpid();
        });

        static::updating(function (Patient $patient) {
            if ($patient->isDirty('dpid')) {
                throw new LogicException('DPID tidak boleh diubah.');
            }
        });
    }

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'is_demo' => 'boolean'];
    }

    /** DPID unik global, jadi dicek tanpa scope workspace. */
    private static function uniqueDpid(): string
    {
        do {
            $dpid = Dpid::generate();
        } while (static::withoutGlobalScopes()->where('dpid', $dpid)->exists());

        return $dpid;
    }

    /** Nama dengan kata terakhir tebal, seperti contoh layar dokter (SRS 5.4). */
    public function displayName(): HtmlString
    {
        $parts = explode(' ', $this->name);
        $last = array_pop($parts);

        return new HtmlString(trim(e(implode(' ', $parts)).' <b>'.e($last).'</b>'));
    }

    public function initials(): string
    {
        $words = explode(' ', $this->name);

        return mb_strtoupper(mb_substr($words[0], 0, 1).(count($words) > 1 ? mb_substr(end($words), 0, 1) : ''));
    }

    public function externalIds(): HasMany
    {
        return $this->hasMany(PatientExternalId::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(OrthoCase::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    public function avatarPhoto(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'avatar_photo_id');
    }
}
