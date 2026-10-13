<?php

namespace App\Models;

use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Audit log append-only (FR-25): siapa melihat, mengunggah, mengubah, mengekspor. */
#[Fillable(['workspace_id', 'user_id', 'action', 'entity_type', 'entity_id', 'meta', 'ip_address', 'user_agent'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Audit log tidak boleh dihapus.'));
    }

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public static function record(string $action, Model $entity, array $meta = []): self
    {
        $request = request();

        return static::create([
            'workspace_id' => $entity->getAttribute('workspace_id') ?? app(CurrentWorkspace::class)->id(),
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => (string) $entity->getKey(),
            'meta' => $meta ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
